<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentIndexRequest;
use App\Http\Resources\PaymentResource;
use App\Services\PaymentService;
use App\Http\Requests\PaymentInitiationRequest;
use App\Http\Resources\PaymentInitiationResource;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use App\Events\InvoiceRequested;
use App\Http\Requests\RefundRequest;
use Illuminate\Support\Facades\Storage;
use App\Events\PaymentSucceeded;
use App\Models\Product;

/**
 * Controller handling all payment-related operations:
 * - Listing payments (admin only)
 * - Initiating a new payment
 * - Receiving Stripe webhook events
 * - Retrieving payment status
 * - Refunding an existing payment
 */
class PaymentController extends Controller
{
    /**
     * The payment business logic service.
     *
     * @var PaymentService
     */
    public function __construct(protected PaymentService $payments) {}

    /**
     * List all payments with optional filters and sorting for admins.
     *
     * @OA\Get(
     *     path="/api/payments",
     *     summary="List payments (only for admins)",
     *     description="Returns a paginated list of payments for administrators.",
     *     operationId="getPayments",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Global search on uuid, invoice_link, transaction_id, user email or user ID",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by payment status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending","paid","failed","refunded"})
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         description="Filter by payment method",
     *         required=false,
     *         @OA\Schema(type="string", enum={"paypal","stripe","free"})
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter payments created on or after this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter payments created on or before this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="amount_min",
     *         in="query",
     *         description="Filter by minimum payment amount",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="amount_max",
     *         in="query",
     *         description="Filter by maximum payment amount",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by column (uuid, amount, paid_at, refunded_at, created_at)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"uuid","amount","paid_at","refunded_at","created_at"}, default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction (asc, desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of payments",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/PaymentResource")
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta",  type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     * )
     *
     * @param  PaymentIndexRequest  $request  Validated query filters and pagination/sort options.
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(PaymentIndexRequest $request)
    {
        $filters = $request->validatedFilters();
        $sortBy    = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $perPage   = (int) $request->query('per_page', 15);

        // Retrieve paginated payments matching filters
        $paginator = $this->payments->paginate(
            $filters,
            $sortBy,
            $sortOrder,
            $perPage
        );

        // Preload related product snapshots for display
        $payments = $paginator->getCollection();

        $allIds = $payments
                    ->flatMap(fn($payment) => collect($payment->cart_snapshot['items'] ?? [])->pluck('product_id'))
                    ->unique()
                    ->toArray();

        $products = Product::with('translations')
                        ->whereIn('id', $allIds)
                        ->get()
                        ->keyBy('id');

        // Attach products to each payment snapshot
        $payments->transform(function($payment) use ($products) {
            $payment->setRelation('snapshot_products', $products);
            return $payment;
        });

        $paginator->setCollection($payments);

        // Return as resource collection
        return PaymentResource::collection($paginator);
    }

    /**
     * Initiate a payment from a user’s cart.
     *
     * Creates a pending payment record and returns Stripe client secret.
     *
     * @OA\Post(
     *     path="/api/payments",
     *     summary="Initiate payment",
     *     description="Creates a new pending payment and returns a Stripe client secret for checkout.",
     *     operationId="storePayment",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Cart identifier and desired payment method",
     *         @OA\JsonContent(
     *             required={"cart_id","payment_method"},
     *             @OA\Property(property="cart_id", type="integer", example=42, description="ID of the cart to pay"),
     *             @OA\Property(property="payment_method", type="string", enum={"stripe","paypal","free"}, example="stripe", description="Payment provider to use")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Payment initiated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="uuid",           type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000", description="Unique payment identifier"),
     *                 @OA\Property(property="status",         type="string", example="pending", description="Initial payment status"),
     *                 @OA\Property(property="transaction_id", type="string", nullable=true, example=null, description="Stripe transaction ID (if available)"),
     *                 @OA\Property(property="client_secret",  type="string", example="pi_1F8Zk2LZ...", description="Stripe client secret for frontend checkout")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=409, ref="#/components/responses/StockUnavailable"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=502, description="Payment gateway error", @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="message", type="string", example="Payment gateway error, please try again later."),
     *         @OA\Property(property="code",    type="string", example="payment_gateway_error")
     *     )),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  PaymentInitiationRequest  $request  Validated cart ID and payment method.
     * @return \Illuminate\Http\JsonResponse        Payment initiation data with status 201.
     */
    public function store(PaymentInitiationRequest $request)
    {
        $data    = $request->validatedData();
        $payment = $this->payments->createFromCart(
            $data['user_id'],
            $data['cart_id'],
            $data['payment_method']
        );

        return (new PaymentInitiationResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Handle incoming Stripe webhook events.
     *
     * Verifies payload signature, updates payment status,
     * and dispatches InvoiceRequested & PaymentSucceeded events on success.
     *
     * @OA\Post(
     *     path="/api/payments/webhook",
     *     summary="Stripe webhook endpoint",
     *     description="Receives and verifies Stripe webhook events, updates payment status, and dispatches downstream events.",
     *     operationId="handleStripeWebhook",
     *     tags={"Payments"},
     *
     *     @OA\Parameter(
     *         name="Stripe-Signature",
     *         in="header",
     *         required=true,
     *         description="Stripe signature header for verifying payload authenticity",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Raw JSON payload sent by Stripe",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Webhook processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid signature or payload",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Invalid signature")
     *         )
     *     ),
     *    @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param  Request  $request  Raw HTTP request with Stripe signature header.
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            \Log::warning('Stripe webhook with invalid signature: '.$e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Throwable $e) {
            \Log::warning('Stripe webhook with invalid payload: '.$e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $object = $event->data->object;

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $uuid = $object->metadata['payment_uuid'] ?? null;

                if (is_string($uuid)) {
                    $payment = $this->payments->markAsPaidByUuid($uuid);

                    if (!empty($payment->wasJustPaid)) {
                        $locale = $payment->cart_snapshot['locale'] ?? config('app.fallback_locale');
                        app()->setLocale($locale);

                    // Generate invoice PDF & tickets
                    event(new InvoiceRequested($payment, $locale));
                    event(new PaymentSucceeded($payment, $locale));
                    } else {
                        \Log::info("Payment {$uuid} already marked as paid, skipping invoice generation.");
                    }
                } else {
                    \Log::warning("Webhook received without payment_uuid on event {$event->type}", [
                        'object_id' => $object->id,
                    ]);
                }
                break;

            case 'payment_intent.payment_failed':
                $intentId = $object->id;
                $this->payments->markAsFailedByIntentId($intentId);
                break;

            default:
                \Log::info("Unhandled Stripe event type: {$event->type}");
        }

        return response()->json([], 200);
    }

    /**
     * Retrieve the status of a payment by UUID for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/payments/{uuid}",
     *     summary="Get payment status",
     *     description="Returns the current status and paid timestamp of a payment for the authenticated user.",
     *     operationId="showPaymentStatus",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Unique identifier of the payment",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="paid", description="Current payment status"),
     *             @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2025-05-10T14:30:00+02:00", description="Timestamp when payment was marked as paid")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  string  $uuid  Unique identifier of the payment.
     * @return \Illuminate\Http\JsonResponse
     */
    public function showStatus(string $uuid)
    {
        $payment = $this->payments->findByUuidForUser($uuid, auth()->id());
        return response()->json([
            'status' => $payment->status->value,
            'paid_at'=> optional($payment->paid_at)->toIso8601String(),
        ], 200);
    }

    /**
     * Refund a payment (partial or full) and regenerate its invoice.
     *
     * Deletes the old PDF, issues the refund, and re-dispatches invoice generation.
     *
     * @OA\Post(
     *     path="/api/payments/{uuid}/refund",
     *     summary="Refund a payment",
     *     description="Allows an administrator to refund a specified amount and regenerates the invoice PDF under the same filename.",
     *     operationId="refundPayment",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="UUID of the payment to refund",
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Amount to refund (must be ≤ original payment amount)",
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 example=25.00,
     *                 description="Amount to refund in the payment’s currency"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment refunded successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="uuid",           type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *             @OA\Property(property="refunded_amount", type="number", format="float", example=25.00, description="Total amount refunded so far"),
     *             @OA\Property(property="status",          type="string", enum={"pending","paid","failed","refunded"}, example="refunded"),
     *             @OA\Property(property="refunded_at",     type="string", format="date-time", example="2025-05-10T15:45:00+02:00", description="Timestamp when refund was processed")
     *         )
     *     ),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=502, description="Payment gateway error", @OA\JsonContent(
     *         type="object",
     *         @OA\Property(property="message", type="string", example="Payment gateway error, please try again later."),
     *         @OA\Property(property="code",    type="string", example="payment_gateway_error")
     *     )),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  RefundRequest  $request  Validated refund amount input.
     * @param  string         $uuid     UUID of the payment to refund.
     * @return \Illuminate\Http\JsonResponse
     */
    public function refund(RefundRequest $request, string $uuid)
    {
        $amount = $request->input('amount');
        $payment = $this->payments->refundByUuid($uuid, $amount);

        // Remove old invoice file if present
        $oldFilename = $payment->invoice_link;
        if (Storage::disk('invoices')->exists($oldFilename)) {
            Storage::disk('invoices')->delete($oldFilename);
        }

        // Regenerate invoice PDF in the correct locale
        $locale = $payment->cart_snapshot['locale'];
        app()->setLocale($locale);
        event(new InvoiceRequested($payment, $locale));

        return response()->json([
            'uuid'            => $payment->uuid,
            'refunded_amount' => $payment->refunded_amount,
            'status'          => $payment->status->value,
            'refunded_at'     => optional($payment->refunded_at)->toIso8601String(),
        ], 200);
    }
}
