<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Product;
use App\Enums\TicketStatus;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use App\Mail\TicketsGenerated;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Enums\PaymentStatus;
use App\Events\InvoiceRequested;
use App\Events\PaymentSucceeded;

class TicketService
{
    /**
     * Get tickets with optional filters and pagination.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getFilteredTickets(array $filters): LengthAwarePaginator
    {
        $query = Ticket::with(['user','payment','product']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        } elseif (!empty($filters['user_email'])) {
            $query->whereHas('user', fn($q) =>
                $q->where('email', $filters['user_email'])
            );
        }

        $applyDateFilter = function(string $column, string $fromKey, string $toKey) use (&$query, $filters) {
            if (!empty($filters[$fromKey])) {
                $query->whereDate($column, '>=', $filters[$fromKey]);
            }
            if (!empty($filters[$toKey])) {
                $query->whereDate($column, '<=', $filters[$toKey]);
            }
        };

        $applyDateFilter('created_at',   'created_from',   'created_to');
        $applyDateFilter('updated_at',   'updated_from',   'updated_to');
        $applyDateFilter('used_at',      'used_from',      'used_to');
        $applyDateFilter('refunded_at',  'refunded_from',  'refunded_to');
        $applyDateFilter('cancelled_at', 'cancelled_from', 'cancelled_to');

        $perPage = $filters['per_page'] ?? 25;
        return $query->orderByDesc('created_at')
                    ->paginate($perPage)
                    ->appends($filters);
    }

    /**
     * Get tickets for the authenticated user.
     *
     * @param  int    $userId
     * @param  array  $filters
     */
    public function getUserTickets(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Ticket::with(['payment','product'])
                    ->where('user_id', $userId);

        // Recherche par nom de produit dans le JSON snapshot
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            // MySQL/Postgres JSON search :
            $query->where('product_snapshot->product_name', 'like', "%{$q}%");
        }

        $perPage = $filters['per_page'] ?? 25;

        return $query->orderByDesc('created_at')
                    ->paginate($perPage)
                    ->appends($filters);
    }

    public function generateForPaymentUuid(string $paymentUuid): void
    {
        $payment = Payment::with('user')
                        ->where('uuid', $paymentUuid)
                        ->firstOrFail();

        $user = $payment->user;
        $createdTickets = [];

        foreach ($payment->cart_snapshot as $itemData) {
            // Retrieve the quantity from the item data or default to 1
            $quantity = $itemData['quantity'] ?? 1;

            $product = Product::find($itemData['product_id']);

            for ($i = 0; $i < $quantity; $i++) {
                $token       = (string) Str::uuid();
                $qrFilename  = "qr_{$token}.png";
                $pdfFilename = "ticket_{$token}.pdf";

                // Generate and store the QR code
                $result = Builder::create()
                    ->writer(new PngWriter())
                    ->data($token)
                    ->encoding(new Encoding('UTF-8'))
                    ->size(300)
                    ->margin(10)
                    ->build();
                Storage::disk('qrcodes')->put($qrFilename, $result->getString());

                $qrDataUri = $result->getDataUri();

                // Generate and store the PDF
                $pdf = Pdf::loadView('tickets.template', [
                    'user'   => $user,
                    // passe l'objet product pour avoir tous ses détails
                    'item'   => (object)[
                        'product'          => $product,
                        'product_snapshot' => $itemData,
                    ],
                    'token'  => $token,
                    'qrDataUri' => $qrDataUri,
                ]);
                Storage::disk('tickets')->put($pdfFilename, $pdf->output());

                // Save the ticket to the database
                $createdTickets[] = Ticket::create([
                    'product_snapshot' => $itemData,
                    'token'            => $token,
                    'qr_filename'      => $qrFilename,
                    'pdf_filename'     => $pdfFilename,
                    'status'           => TicketStatus::Issued->value,
                    'user_id'          => $user->id,
                    'payment_id'       => $payment->id,
                    'product_id'       => $itemData['product_id'],
                ]);
            }
        }

        // Mail sending
        Mail::to($user->email)
            ->send(new TicketsGenerated($user, $createdTickets));
    }

    /**
     * Change the status of a ticket.
     *
     * @param  int    $ticketId
     * @param  string $status
     * @return Ticket
     */
    public function changeStatus(int $ticketId, string $status): Ticket
    {
        $ticket = Ticket::findOrFail($ticketId);

        // Update the status and timestamps based on the new status
        $now = Carbon::now();
        $timestamps = [
            TicketStatus::Used->value      => ['status' => $status, 'used_at'      => $now],
            TicketStatus::Refunded->value  => ['status' => $status, 'refunded_at'  => $now],
            TicketStatus::Cancelled->value => ['status' => $status, 'cancelled_at' => $now],
            TicketStatus::Issued->value    => ['status' => $status], // No timestamp update
        ];

        $data = $timestamps[$status] ?? ['status' => $status];
        $ticket->update($data);

        return $ticket;
    }

    /**
     * Create free tickets for a user and product.
     *
     * @param  int $userId
     * @param  int $productId
     * @param  int $quantity
     */
    public function createFreeTickets(int $userId, int $productId, int $quantity): void
    {
        $user    = User::findOrFail($userId);
        $product = Product::findOrFail($productId);

        $itemData = [
            'product_id'       => $product->id,
            'product_name'     => $product->name,
            'ticket_type'      => $product->product_details['category'],
            'quantity'         => $quantity,
            'unit_price'       => $product->price,
            'discount_rate'    => 1.0,
            'discounted_price' => 0.0,
        ];

        // 1) Create the payment free with the invoice link
        $invoiceFilename = 'invoice_'.Str::uuid().'.pdf';

        $payment = Payment::create([
            'user_id'       => $user->id,
            'status'        => PaymentStatus::Paid->value,
            'payment_method'=> 'free',
            'amount'        => 0.0,
            'cart_snapshot' => [$itemData],
            'invoice_link'  => $invoiceFilename,
        ]);

        // 2) Generate the invoice PDF
        event(new InvoiceRequested($payment));

        // 3) Generate the tickets
        event(new PaymentSucceeded($payment));
    }
}
