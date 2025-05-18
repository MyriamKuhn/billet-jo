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
        Event::fake();

        // Crée un Payment factice pour l'event
        $paymentModel = \App\Models\Payment::factory()->create([
            'transaction_id' => 'pi_123',
            'status'         => \App\Enums\PaymentStatus::Paid,
        ]);

        // Stub du service pour vérifier l'appel markAsPaidByUuid
        $mockService = $this->mock(\App\Services\PaymentService::class);
        $mockService->shouldReceive('markAsPaidByUuid')
                    ->once()
                    ->with('uuid-123')
                    ->andReturn($paymentModel);

        // Prépare le payload et le header
        $payloadArray = [
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id'       => 'pi_123',
                    'metadata' => ['payment_uuid' => 'uuid-123'],
                ],
            ],
        ];
        $body      = json_encode($payloadArray);
        $timestamp = time();
        $header    = $this->makeStripeSignatureHeader($body, $this->stripeSecret, $timestamp);

        // Envoi en JSON via postJson()
        $this->postJson('/api/payments/webhook', $payloadArray, [
            'Stripe-Signature' => $header,
        ])->assertStatus(200)
        ->assertExactJson([]);

        // Vérifie que l’événement a bien été dispatché avec le bon Payment
        Event::assertDispatched(InvoiceRequested::class, function ($e) use ($paymentModel) {
            return $e->payment->is($paymentModel);
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
        // Prévoir l'appel au logger
        Log::shouldReceive('warning')
        ->once()
        ->withArgs(function ($message, $context) {
            return str_contains($message, 'Webhook received without payment_uuid')
                && isset($context['object_id'])
                && $context['object_id'] === 'pi_456';
        });

        // On ne dispatch pas InvoiceRequested
        Event::fake([InvoiceRequested::class]);

        // Prépare le payload et le header
        $payloadArray = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'       => 'pi_456',
                    'metadata' => [],  // pas de payment_uuid
                ],
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

        // Aucun InvoiceRequested n'est dispatché
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

