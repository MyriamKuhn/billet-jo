<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\PaymentService;
use App\Events\InvoiceRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\StripeClient;
use Stripe\Signature;
use Stripe\Exception\SignatureVerificationException;
use Mockery;
use UnexpectedValueException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $stripeSecret;

    protected function setUp(): void
    {
        parent::setUp();

        // Utilise un secret fixe pour générer les headers de test
        config(['services.stripe.webhook_secret' => $this->stripeSecret = 'whsec_test_secret']);
    }

    /**
     * Helper pour générer un Stripe-Signature header manuellement.
     *
     * @param string $payload   Le corps brut envoyé
     * @param string $secret    Le signing secret configuré
     * @param int    $timestamp Un timestamp UNIX
     * @return string
     */
    private function makeStripeSignatureHeader(string $payload, string $secret, int $timestamp): string
    {
        $signedPayload = "{$timestamp}.{$payload}";
        $sig = hash_hmac('sha256', $signedPayload, $secret);
        return "t={$timestamp},v1={$sig}";
    }

    public function testItReturns400OnInvalidSignature()
    {
        // On envoie un tableau, pas une chaîne JSON
        $payload = ['type' => 'any.event', 'data' => []];

        $this->postJson('/api/payments/webhook', $payload, [
            'Stripe-Signature' => 'invalid_header',
        ])->assertStatus(400)
        ->assertJson(['error' => 'Invalid signature']);
    }

    public function testItReturns400OnInvalidPayload()
    {
        // On veut un vrai header, mais un payload non-JSON
        $raw       = 'not a json';
        $timestamp = time();
        $header    = $this->makeStripeSignatureHeader($raw, $this->stripeSecret, $timestamp);

        // Envoi du contenu brut avec post()
        $this->withHeaders([
            'Stripe-Signature' => $header,
            'Content-Type'     => 'application/json',
        ])->post('/api/payments/webhook', [], [], [], [], $raw)
        ->assertStatus(400)
        ->assertJson(['error' => 'Invalid signature']);
    }

    public function testItProcessesSucceededEventAndDispatchesInvoiceRequested()
    {
        // 1) Fake all events
        Event::fake();

        // 2) Create a Payment *with* the uuid that your metadata will carry
        $payment = \App\Models\Payment::factory()->create([
            'uuid'           => 'uuid-123',       // ← must match the metadata
            'transaction_id' => 'pi_123',
            'status'         => \App\Enums\PaymentStatus::Pending,
        ]);

        // 3) Partial‐mock so the real markAsPaidByUuid() runs (finds your record) and dispatches InvoiceRequested
        $this->partialMock(\App\Services\PaymentService::class, function ($mock) {
            $mock->shouldReceive('markAsPaidByUuid')
                ->once()
                ->with('uuid-123')
                ->passthru();
        });

        // 4) Build the Stripe payload & header
        $payload = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'       => 'pi_123',
                    'metadata' => ['payment_uuid' => 'uuid-123'],
                ],
            ],
        ];
        $body      = json_encode($payload);
        $timestamp = time();
        $secret    = config('services.stripe.webhook_secret');
        $sig       = $this->makeStripeSignatureHeader($body, $secret, $timestamp);

        // 5) (Optional) Disable only the Stripe‐signature check if you have a middleware for it.
        //    If you don’t, you can safely omit this line.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyStripeSignature::class);

        // 6) Hit the route (hard‑coded or via route helper—here hard‑coded)
        $this->postJson('/api/payments/webhook', $payload, [
            'Stripe-Signature' => $sig,
        ])
        ->assertStatus(200)
        ->assertExactJson([]);  // your controller always returns [] on success

        // 7) Finally, assert that InvoiceRequested was dispatched with the right model
        Event::assertDispatched(\App\Events\InvoiceRequested::class, function ($e) use ($payment) {
            return $e->payment->is($payment);
        });
    }

    public function testItProcessesFailedEventAndCallsMarkAsFailed()
    {
        // Stub du service pour vérifier markAsFailedByIntentId
        $mockService = $this->mock(\App\Services\PaymentService::class);
        $mockService->shouldReceive('markAsFailedByIntentId')
                    ->once()
                    ->with('pi_fail');

        // Prépare le payload et le header manuellement
        $payloadArray = [
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => ['id' => 'pi_fail'],
            ],
        ];
        $body      = json_encode($payloadArray);
        $timestamp = time();
        $header    = $this->makeStripeSignatureHeader($body, $this->stripeSecret, $timestamp);

        // Envoi via postJson avec le tableau
        $this->postJson('/api/payments/webhook', $payloadArray, [
            'Stripe-Signature' => $header,
        ])->assertStatus(200)
        ->assertExactJson([]);
    }

    public function testItLogsWarningAndReturns200WhenUuidMissingInSucceededEvent()
    {
        Log::shouldReceive('warning')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Webhook received without payment_uuid')
                && $context['object_id'] === 'pi_456';
        });

        Event::fake([InvoiceRequested::class]);

        $payload = [
            'type' => 'payment_intent.succeeded',        // ← not checkout.session.completed
            'data' => ['object' => [
                'id'       => 'pi_456',
                'metadata' => [],                       // no payment_uuid
            ]],
        ];
        $body      = json_encode($payload);
        $timestamp = time();
        $secret    = config('services.stripe.webhook_secret');
        $sig       = $this->makeStripeSignatureHeader($body, $secret, $timestamp);

        // **Bypass all middleware** so Laravel definitely matches the route
        $this->withoutMiddleware();

        $this->postJson('/api/payments/webhook', $payload, [
            'Stripe-Signature' => $sig,
        ])
        ->assertStatus(200)
        ->assertExactJson([]);

        Event::assertNotDispatched(InvoiceRequested::class);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItReturns400AndLogsWarningOnInvalidPayload()
    {
        // 1) On mock la classe Stripe\Webhook pour que constructEvent jette
        Mockery::mock('alias:Stripe\Webhook')
            ->shouldReceive('constructEvent')
            ->once()
            ->andThrow(new UnexpectedValueException('breaker'));

        // 2) On ignore les appels à Log::error()
        Log::shouldReceive('error')->andReturnNull();

        // 3) On s'attend au warning payload
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function($msg) {
                return str_contains($msg, 'Stripe webhook with invalid payload');
            });

        // 4) Prépare un vrai header (payload JSON correct)
        $payloadArray = ['foo' => 'bar'];
        $body         = json_encode($payloadArray);
        $timestamp    = time();
        $header       = $this->makeStripeSignatureHeader($body, $this->stripeSecret, $timestamp);

        // 5) Appel HTTP
        $this->postJson('/api/payments/webhook', $payloadArray, [
            'Stripe-Signature' => $header,
        ])
        ->assertStatus(400)
        ->assertJson(['error' => 'Invalid payload']);

        Mockery::close();
    }

    public function testItLogsInfoAndReturns200ForUnhandledEventTypes()
    {
        // On ne veut pas de dispatch ni d'erreur
        Event::fake();
        $mockService = $this->mock(PaymentService::class);
        // On ne s'attend à aucun appel sur le service

        // Prépare un event de type inconnu
        $payloadArray = [
            'type' => 'some.random.event',
            'data' => ['object' => ['id' => 'xyz']],
        ];
        $body      = json_encode($payloadArray);
        $timestamp = time();
        $header    = $this->makeStripeSignatureHeader($body, $this->stripeSecret, $timestamp);

        // On s'attend au log info pour le type non géré
        Log::shouldReceive('info')
            ->once()
            ->with("Unhandled Stripe event type: some.random.event");

        $this->postJson('/api/payments/webhook', $payloadArray, [
                'Stripe-Signature' => $header,
            ])
            ->assertStatus(200)
            ->assertExactJson([]);

        // Aucun InvoiceRequested dispatché
        Event::assertNotDispatched(InvoiceRequested::class);
    }

}

