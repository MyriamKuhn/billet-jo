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

class PaymentService
{
    private CartService $cartService;

    public function __construct(protected StripeClient $stripe, CartService $cartService) {
        $this->cartService = $cartService;
    }

    /**
     * Paginate the payments with optional filters and sorting.
     *
     * @param  array  $filters    Indexed array of filters
     * @param  string $sortBy     Columne to sort by
     * @param  string $sortOrder  Sort order (asc or desc)
     * @param  int    $perPage    Number of items per page
     * @return LengthAwarePaginator
     */
    public function paginate(
        array $filters,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Payment::with('user');

        // Global search filter
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

        // Simple filters
        foreach (['status','payment_method','user_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        // Date filters
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Amount filters
        if (! empty($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }
        if (! empty($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        // Dynamic sorting and pagination
        return $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }

    /**
     * Create a payment from a cart.
     *
     * @param  int     $userId    ID of the authenticated user
     * @param  int     $cartId    ID of the cart to be paid
     * @param  string  $method    Payment method (e.g. 'stripe', 'paypal')
     * @return \App\Models\Payment
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function createFromCart(int $userId, int $cartId, string $method):Payment
    {
        DB::beginTransaction();

        try {
            // 1) Idempotency: check for an existing pending payment for this user/cart
            $existing = Payment::where('user_id', $userId)
                ->where('status', PaymentStatus::Pending)
                ->whereJsonContains('cart_snapshot', ['cart_id' => $cartId])
                ->first();

            if ($existing) {
                DB::commit();
                return $existing;
            }

            // 2) Create a new pending payment record in the database
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

            // 3) Inline Stripe call to create a PaymentIntent
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
                // Log the Stripe error, mark payment as failed, and abort with 502
                Log::error('Stripe PaymentIntent creation failed', [
                    'exception'  => $e,
                    'payment_id' => $payment->id,
                ]);
                $payment->update(['status' => PaymentStatus::Failed]);
                DB::commit();
                abort(502, 'Payment gateway error, please try again later');
            }

            // 4) Finalize the payment by storing Stripe transaction details
            $payment->update([
                'transaction_id' => $intent->id,
                'client_secret'  => $intent->client_secret,
            ]);

            DB::commit();
            return $payment;

        } catch (\Throwable $e) {
            DB::rollBack();
            // If it's an HTTP exception, rethrow it
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            // Otherwise, log unexpected errors and return a generic 500
            Log::error('Unexpected payment error', ['exception' => $e]);
            abort(500, 'Internal payment error');
        }
    }

    /**
     * Load the cart with its items and related products, ensuring it belongs to the user.
     *
     * @param  int  $userId  ID of the authenticated user
     * @param  int  $cartId  ID of the cart to load
     * @return \App\Models\Cart
     */
    private function loadCart(int $userId, int $cartId): Cart
    {
        return Cart::with('cartItems.product')
                    ->where('user_id', $userId)
                    ->findOrFail($cartId);
    }

    /**
     * Build a snapshot of the cart items for invoice and payment processing.
     *
     * @param  \App\Models\Cart  $cart
     * @return array<int, array{product_id:int, product_name:string, ticket_type:string, quantity:int, unit_price:float, discount_rate:float, discounted_price:float}>
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
            'locale' => app()->getLocale(),  // ← on y stocke la locale courante
            'items'  => $lines,
        ];
    }

    /**
     * Calculate the total payment amount based on the snapshot.
     *
     * @param  array<int, array{ticket_type:string,quantity:int,unit_price:float,discount_rate:float,discounted_price:float}>  $snapshot
     * @return float  Total amount (sum of discounted_price * quantity)
     */
    private function calculateAmount(array $snapshot): float
    {
        return collect($snapshot['items'])
                    ->reduce(fn($total, $line) => $total + ($line['discounted_price'] * $line['quantity']), 0.0);
    }

    /**
     * Retrieve a payment by UUID for a given user.
     *
     * @param  string  $uuid     Unique identifier of the payment
     * @param  int     $userId   ID of the user who owns the payment
     * @return \App\Models\Payment
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
     * Mark a payment as paid using its UUID (from Checkout Session metadata).
     *
     * @param  string  $uuid  Unique identifier of the payment
     * @return \App\Models\Payment
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
            // Ajout dynamique d’une propriété pour usage immédiat
            $payment->wasJustPaid = true;
        } else {
            // Déjà payé précédemment
            $payment->wasJustPaid = false;
        }
        return $payment;
    }

    /**
     * Mark a payment as paid using the Stripe PaymentIntent ID.
     *
     * @param  string  $intentId  Stripe PaymentIntent identifier
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
     * Mark a payment as failed using the Stripe PaymentIntent ID.
     *
     * @param  string  $intentId  Stripe PaymentIntent identifier
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
     * Transition a payment from Pending to Paid.
     *
     * @param  \App\Models\Payment  $payment  The payment instance to update
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
     * Refund a payment by UUID.
     *
     * @param  string  $uuid
     * @param  float   $amount
     * @return \App\Models\Payment
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
