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
}
