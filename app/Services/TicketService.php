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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Enums\PaymentStatus;
use App\Events\InvoiceRequested;
use App\Events\PaymentSucceeded;
use App\Exceptions\TicketAlreadyProcessedException;

/**
 * Service class for managing tickets.
 */
class TicketService
{
    /**
     * Retrieve tickets with optional filters and pagination.
     *
     * @param  array  $filters
     * @return LengthAwarePaginator
     */
    public function getFilteredTickets(array $filters): LengthAwarePaginator
    {
        $query = Ticket::query();

        // 1) Global search on token, product name, or ticket type
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function($qb) use ($q) {
                $qb->where('token', 'like', "%{$q}%")
                ->orWhere('product_snapshot->product_name', 'like', "%{$q}%")
                ->orWhere('product_snapshot->ticket_type', 'like', "%{$q}%");
            });
        }

        // 2) Simple filters
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        } elseif (! empty($filters['user_email'])) {
            $query->whereHas('user', fn($q) =>
                $q->where('email', $filters['user_email'])
            );
        }
        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        if (! empty($filters['payment_uuid'])) {
            $query->where('payment_id', function($q) use ($filters) {
                $q->select('id')
                ->from('payments')
                ->where('uuid', $filters['payment_uuid'])
                ->limit(1);
            });
        }

        // 3) Date filters helper
        $applyDate = fn($column,$from,$to) =>
            tap($query, function($q) use ($column,$from,$to,$filters) {
                if (! empty($filters[$from])) {
                    $q->whereDate($column, '>=', $filters[$from]);
                }
                if (! empty($filters[$to])) {
                    $q->whereDate($column, '<=', $filters[$to]);
                }
            });
        $applyDate('created_at','created_from','created_to');
        $applyDate('updated_at','updated_from','updated_to');
        $applyDate('used_at','used_from','used_to');
        $applyDate('refunded_at','refunded_from','refunded_to');
        $applyDate('cancelled_at','cancelled_from','cancelled_to');

        // 4) Pagination
        $perPage = $filters['per_page'] ?? 25;
        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($filters);
    }

    /**
     * Retrieve tickets for a specific user with filters and pagination.
     *
     * @param  int    $userId
     * @param  array  $filters
     * @return LengthAwarePaginator
     */
    public function getUserTickets(int $userId, array $filters): LengthAwarePaginator
    {
        $query = Ticket::with(['payment','product'])
                    ->where('user_id', $userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by event date range
        if (!empty($filters['event_date_from']) || !empty($filters['event_date_to'])) {
            $query->whereHas('product', function($qProd) use ($filters) {
                if (!empty($filters['event_date_from'])) {
                    $dateFrom = $filters['event_date_from'];
                    $qProd->whereDate(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(product_details, '$.date'))"),
                        '>=',
                        $dateFrom
                    );
                }
                if (!empty($filters['event_date_to'])) {
                    $dateTo = $filters['event_date_to'];
                    $qProd->whereDate(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(product_details, '$.date'))"),
                        '<=',
                        $dateTo
                    );
                }
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderByDesc('created_at');

        $perPage = isset($filters['per_page']) ? (int)$filters['per_page'] : 25;

        return $query->paginate($perPage)->appends($filters);
    }

    /**
     * Generate tickets for a given payment UUID and locale.
     *
     * @param  string  $paymentUuid
     * @param  string  $locale
     * @return void
     */
    public function generateForPaymentUuid(string $paymentUuid, string $locale): void
    {
        $payment = Payment::with('user')
                        ->where('uuid', $paymentUuid)
                        ->firstOrFail();

        /** @var \App\Models\User $user */
        $user = $payment->user;
        $createdTickets = [];

        app()->setLocale($locale);

        DB::transaction(function() use ($payment, $user, &$createdTickets) {
            foreach ($payment->cart_snapshot['items'] as $itemData) {
                $productId = $itemData['product_id'];
                $quantity = $itemData['quantity'] ?? 1;

                $product = Product::findOrFail($productId);

                // Decrease stock for the product
                $product->decrement('stock_quantity', $quantity);

                for ($i = 0; $i < $quantity; $i++) {
                    $token       = (string) Str::uuid();
                    $qrFilename  = "qr_{$token}.png";
                    $pdfFilename = "ticket_{$token}.pdf";

                    // Build and save QR code image
                    $result = Builder::create()
                        ->writer(new PngWriter())
                        ->data($token)
                        ->encoding(new Encoding('UTF-8'))
                        ->size(300)
                        ->margin(10)
                        ->build();
                    Storage::disk('qrcodes')->put($qrFilename, $result->getString());

                    $qrDataUri = $result->getDataUri();

                    // Render and save ticket PDF
                    $pdf = Pdf::loadView('tickets.template', [
                        'user'   => $user,
                        'item'   => (object)[
                            'product'          => $product,
                            'product_snapshot' => $itemData,
                        ],
                        'token'  => $token,
                        'qrDataUri' => $qrDataUri,
                    ]);
                    Storage::disk('tickets')->put($pdfFilename, $pdf->output());

                    // Store the ticket record
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
        });

        // Send notification email with generated tickets
        Mail::to($user->email)
            ->send(new TicketsGenerated($user, $createdTickets));
    }

    /**
     * Change the status of a ticket and adjust stock if needed.
     *
     * @param  int     $ticketId
     * @param  string  $status
     * @return Ticket
     */
    public function changeStatus(int $ticketId, string $status): Ticket
    {
        $ticket = Ticket::findOrFail($ticketId);
        $now = Carbon::now();

        // Map status to the corresponding timestamp field
        $timestamps = [
            TicketStatus::Used->value      => ['status' => $status, 'used_at'      => $now],
            TicketStatus::Refunded->value  => ['status' => $status, 'refunded_at'  => $now],
            TicketStatus::Cancelled->value => ['status' => $status, 'cancelled_at' => $now],
            TicketStatus::Issued->value    => ['status' => $status], // No timestamp update
        ];

        $data = $timestamps[$status] ?? ['status' => $status];

        DB::transaction(function() use ($ticket, $data, $status) {
            // 1) Update the ticket
            $ticket->update($data);

            // 2) If cancelled or refunded, restore one unit to product stock
            if (in_array($status, [TicketStatus::Cancelled->value, TicketStatus::Refunded->value], true)) {
                $ticket->product->increment('stock_quantity', 1);
            }
        });

        return $ticket;
    }

    /**
     * Create free tickets (no payment) for a user and product.
     *
     * @param  int     $userId
     * @param  int     $productId
     * @param  int     $quantity
     * @param  string  $locale
     * @return void
     */
    public function createFreeTickets(int $userId, int $productId, int $quantity, string $locale): void
    {
        $user    = User::findOrFail($userId);
        $product = Product::findOrFail($productId);

        app()->setLocale($locale);

        $itemData = [
            'product_id'       => $product->id,
            'product_name'     => $product->name,
            'ticket_type'      => $product->product_details['category'],
            'ticket_places'    => $product->product_details['places'],
            'quantity'         => $quantity,
            'unit_price'       => $product->price,
            'discount_rate'    => 1.0,
            'discounted_price' => 0.0,
        ];

        // 1) Create a "free" payment record
        $invoiceFilename = 'invoice_'.Str::uuid().'.pdf';

        $payment = Payment::create([
            'uuid'          => Str::uuid()->toString(),
            'user_id'       => $user->id,
            'status'        => PaymentStatus::Paid->value,
            'payment_method'=> 'free',
            'amount'        => 0.0,
            'cart_snapshot' => [
                'items' => [ $itemData ]
            ],
            'invoice_link'  => $invoiceFilename,
        ]);

        // 2) Trigger invoice generation
        event(new InvoiceRequested($payment, $locale));

        // 3) Trigger ticket generation
        event(new PaymentSucceeded($payment, $locale));
    }

    /**
     * Retrieve ticket info by its QR code filename token.
     *
     * @param  string  $token
     * @return array
     */
    public function getInfoByQrToken(string $token): array
    {
        $qrFilename = "qr_{$token}.png";

        $ticket = Ticket::with(['user','product'])
                        ->where('qr_filename', $qrFilename)
                        ->firstOrFail();

        return [
            'token'  => $ticket->token,
            'status' => $ticket->status,
            'user'   => [
                'firstname' => $ticket->user->firstname,
                'lastname'  => $ticket->user->lastname,
                'email'     => $ticket->user->email,
            ],
            'event'  => [
                'name'     => $ticket->product->name,
                'date'     => $ticket->product->product_details['date'] ?? null,
                'time'     => $ticket->product->product_details['time'] ?? null,
                'location' => $ticket->product->product_details['location'] ?? null,
                'places'   => $ticket->product->product_details['places'] ?? null,
            ],
        ];
    }

    /**
     * Scan and validate a ticket by its token.
     *
     * @param  string  $token
     * @return array
     * @throws TicketAlreadyProcessedException
     */
    public function scanAndValidate(string $token): array
    {
        $ticket = Ticket::with('user','product')
                        ->where('token', $token)
                        ->firstOrFail();

        if ($ticket->status !== TicketStatus::Issued) {
            throw new TicketAlreadyProcessedException($ticket);
        }

        $ticket->update([
            'status'  => TicketStatus::Used->value,
            'used_at' => now(),
        ]);

        return [
            'status'  => $ticket->status,
            'used_at' => $ticket->used_at,
        ];
    }

    /**
     * Return sales statistics grouped by product, with pagination, search, and sorting.
     *
     * @param  array  $filters  Supported keys: 'q', 'sort_by', 'sort_order', 'per_page'
     * @return LengthAwarePaginator
     */
    public function getSalesStats(array $filters): LengthAwarePaginator
    {
        $query = Ticket::query()
            ->select('product_id', DB::raw('COUNT(*) as sales_count'))
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->groupBy('product_id')
            ->join('products', 'products.id', '=', 'tickets.product_id');

        if (!empty($filters['q'])) {
            $query->where('products.name', 'like', "%{$filters['q']}%");
        }

        // Apply sorting
        $sortBy    = $filters['sort_by']    ?? 'sales_count';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage)->appends($filters);
    }
}
