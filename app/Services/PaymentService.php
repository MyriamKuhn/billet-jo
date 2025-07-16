<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Cart;
use Illuminate\Support\Str;
use App\Enums\PaymentStatus;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PaymentService handles payment-related operations such as creating payments from carts,
 * paginating payments with filters, and managing payment statuses.
 */
class PaymentService
{
    private CartService $cartService;

    public function __construct(protected StripeClient $stripe, CartService $cartService) {
        $this->cartService = $cartService;
    }

    /**
     * Paginate payments with optional filters and sorting.
     *
     * @param  array   $filters    Associative array of filters.
     * @param  string  $sortBy     Column to sort by.
     * @param  string  $sortOrder  Sort direction ('asc' or 'desc').
     * @param  int     $perPage    Items per page.
     * @return LengthAwarePaginator
     */
    public function paginate(
        array $filters,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Payment::with('user');

        // Global search filter across UUID, invoice link, transaction ID, and user email/ID
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function($qBuilder) use ($q) {
                $qBuilder->where('uuid', 'like', "%{$q}%")
                    ->orWhere('invoice_link', 'like', "%{$q}%")
                    ->orWhere('transaction_id', 'like', "%{$q}%")
                    ->orWhereHas('user', fn($u) =>
                        $u->where('email', 'like', "%{$q}%")
                            ->orWhere('id', $q)
                    );
            });
        }

        // Simple equality filters for status, payment_method, and user_id
        foreach (['status','payment_method','user_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Date range filters on created_at
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Numeric range filters on amount
        if (! empty($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }
        if (! empty($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        // Apply sorting and return paginated result
        return $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Create a payment record from a cart.
     *
     * @param  int     $userId    Authenticated user ID.
     * @param  int     $cartId    Cart ID to be paid.
     * @param  string  $method    Payment method ('stripe', 'paypal', etc.).
     * @return Payment
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function createFromCart(int $userId, int $cartId, string $method):Payment
    {
        DB::beginTransaction();

        try {
            // 1) Idempotency: reuse any existing pending payment for this user and cart
            $existing = Payment::where('user_id', $userId)
                ->where('status', PaymentStatus::Pending)
                ->whereJsonContains('cart_snapshot', ['cart_id' => $cartId])
                ->first();

            if ($existing) {
                DB::commit();
                return $existing;
            }

            // 2) Prepare a new pending payment record
            $cart     = $this->loadCart($userId, $cartId);
            $this->cartService->assertStockAvailable($cart);
            $rawSnapshot = $this->buildSnapshot($cart);
            $amount   = $this->calculateAmount($rawSnapshot);

            $uuid           = (string) Str::uuid();
            $filename       = "invoice_{$uuid}.pdf";
            $snapshotWithId = array_merge($rawSnapshot, ['cart_id' => $cartId]);

            $payment = Payment::create([
                'uuid'           => $uuid,
                'invoice_link'   => $filename,
                'cart_snapshot'  => $snapshotWithId,
                'amount'         => $amount,
                'payment_method' => $method,
                'status'         => PaymentStatus::Pending,
                'user_id'        => $userId,
            ]);

            // 3) Create a Stripe PaymentIntent
            try {
                $intent = $this->stripe->paymentIntents->create([
                    'amount'               => intval($payment->amount * 100),
                    'currency'             => 'eur',
                    'payment_method_types' => ['card'],
                    'metadata'             => [
                        'payment_uuid' => $payment->uuid,
                        'cart_id'      => $cartId,
                        'user_id'      => $userId,
                    ],
                ]);
            } catch (ApiErrorException $e) {
                // On Stripe error, mark as failed and abort with a 502
                Log::error('Stripe PaymentIntent creation failed', [
                    'exception'  => $e,
                    'payment_id' => $payment->id,
                ]);
                $payment->update(['status' => PaymentStatus::Failed]);
                DB::commit();
                abort(502, 'Payment gateway error, please try again later');
            }

            // 4) Store Stripe intent details and commit
            $payment->update([
                'transaction_id' => $intent->id,
                'client_secret'  => $intent->client_secret,
            ]);

            DB::commit();
            return $payment;

        } catch (\Throwable $e) {
            DB::rollBack();
            // Rethrow HTTP exceptions
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            // Log and abort on unexpected errors
            Log::error('Unexpected payment error', ['exception' => $e]);
            abort(500, 'Internal payment error');
        }
    }

    /**
     * Load the cart with its items and products, ensuring ownership.
     *
     * @param  int  $userId  Authenticated user ID.
     * @param  int  $cartId  Cart ID.
     * @return Cart
     */
    private function loadCart(int $userId, int $cartId): Cart
    {
        return Cart::with('cartItems.product')
                    ->where('user_id', $userId)
                    ->findOrFail($cartId);
    }

    /**
     * Build a snapshot of the cart for invoice and payment processing.
     *
     * @param  Cart  $cart
     * @return array{
     *   locale: string,
     *   items: array<int, array{
     *       product_id:int,
     *       product_name:string,
     *       ticket_type:string,
     *       ticket_places:int,
     *       quantity:int,
     *       unit_price:float,
     *       discount_rate:float,
     *       discounted_price:float
     *   }>
     * }
     */
    private function buildSnapshot(Cart $cart): array
    {
        $cart->load(['cartItems.product.translations']);

        $lines = $cart->cartItems->map(fn($item) => [
            'product_id'       => $item->product_id,
            'product_name'     => $item->product->name,
            'ticket_type'      => $item->product->product_details['category'],
            'ticket_places'    => $item->product->product_details['places'],
            'quantity'         => $item->quantity,
            'unit_price'       => (float) $item->product->price,
            'discount_rate'    => (float) $item->product->sale,
            'discounted_price' => round($item->product->price * (1 - $item->product->sale), 2),
        ])->toArray();

        return [
            'locale' => app()->getLocale(),  // store the current locale
            'items'  => $lines,
        ];
    }

    /**
     * Calculate the total amount from the cart snapshot.
     *
     * @param  array{items: array<int, array{discounted_price:float,quantity:int}>}  $snapshot
     * @return float  Sum of (discounted_price × quantity).
     */
    private function calculateAmount(array $snapshot): float
    {
        return collect($snapshot['items'])
                    ->reduce(fn($total, $line) => $total + ($line['discounted_price'] * $line['quantity']), 0.0);
    }

    /**
     * Find a payment by UUID for a given user.
     *
     * @param  string  $uuid    Payment UUID.
     * @param  int     $userId  Owning user ID.
     * @return Payment
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByUuidForUser(string $uuid, int $userId): Payment
    {
        return Payment::where('uuid', $uuid)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    /**
     * Mark a payment as paid by its UUID.
     *
     * @param  string  $uuid  Payment UUID.
     * @return Payment
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function markAsPaidByUuid(string $uuid): Payment
    {
        $payment = Payment::where('uuid', $uuid)->firstOrFail();
        if ($payment->status !== PaymentStatus::Paid) {
            $payment->update([
                'status'  => PaymentStatus::Paid,
                'paid_at' => now(),
            ]);
            $payment->refresh();
            // Dynamically add a flag to indicate just-paid status
            $payment->wasJustPaid = true;
        } else {
            $payment->wasJustPaid = false;
        }
        return $payment;
    }

    /**
     * Mark a payment as paid by its Stripe PaymentIntent ID.
     *
     * @param  string  $intentId  Stripe PaymentIntent ID.
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function markAsPaidByIntentId(string $intentId): void
    {
        $payment = Payment::where('transaction_id', $intentId)->firstOrFail();
        $this->markAsPaid($payment);
    }

    /**
     * Mark a payment as failed by its Stripe PaymentIntent ID.
     *
     * @param  string  $intentId  Stripe PaymentIntent ID.
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function markAsFailedByIntentId(string $intentId): void
    {
        $payment = Payment::where('transaction_id', $intentId)->firstOrFail();
        if ($payment->status !== PaymentStatus::Failed) {
            $payment->update([
                'status' => PaymentStatus::Failed,
            ]);
        }
    }

    /**
     * Transition a pending payment to paid.
     *
     * @param  Payment  $payment  The payment to update.
     * @return void
     */
    public function markAsPaid(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }
        $payment->update([
            'status'  => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * Issue a refund for a payment by UUID.
     *
     * @param  string  $uuid    Payment UUID.
     * @param  float   $amount  Amount to refund.
     * @return Payment
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function refundByUuid(string $uuid, float $amount): Payment
    {
        // Wrap in a DB transaction to ensure atomicity
        return DB::transaction(function () use ($uuid, $amount) {
            // 1) Retrieve the payment
            $payment = Payment::where('uuid', $uuid)->firstOrFail();

            // 2) Compute new total refunded amount
            $alreadyRefunded = $payment->refunded_amount ?? 0.0;
            $remaining       = $payment->amount - $alreadyRefunded;
            $toRefund        = min($amount, $remaining);

            // 3) Call Stripe to issue the refund
            try {
                $stripeRefund = $this->stripe->refunds->create([
                    'payment_intent' => $payment->transaction_id,
                    'amount'         => intval($toRefund * 100), // in cents
                ]);
            } catch (ApiErrorException $e) {
                Log::error('Stripe refund failed', [
                    'exception'  => $e,
                    'payment_id' => $payment->id,
                    'amount'     => $toRefund,
                ]);
                abort(502, 'Payment gateway error, please try again later.');
            }

            // 4) Update the payment record
            $newRefundedTotal = $alreadyRefunded + $toRefund;
            $data = [
                'refunded_amount' => $newRefundedTotal,
                'refunded_at'     => now(),
            ];

            // If we've refunded the full original amount, mark status as refunded
            if (abs($newRefundedTotal - $payment->amount) < 0.01) {
                $data['status'] = PaymentStatus::Refunded;
            }

            $payment->update($data);

            // 5) Return the updated payment
            return $payment;
        });
    }
}
