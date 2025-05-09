<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Payment;
use App\Enums\PaymentStatus;
use App\Services\PaymentService;
use App\Events\InvoiceRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Mockery;
use Stripe\StripeClient;
use App\Models\Cart;
use Illuminate\Support\Str;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testIndexRequiresAdminAndReturnsPaginatedList()
    {
        $user   = User::factory()->create(['role' => 'user']);
        $admin  = User::factory()->create(['role' => 'admin']);
        // Create some payments
        Payment::factory()->count(3)->create();

        // Non-admin should get 403
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/payments')
            ->assertStatus(403);

        // Admin: real service works since paginate doesn't call Stripe
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/payments?per_page=2')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['uuid','amount','status','user']],
                'links',
                'meta'
            ])
            ->assertJsonCount(2, 'data');
    }

    public function testStoreReturns201AndPaymentInitiationResource()
    {
        $user = User::factory()->create();

        // Crée un cart pour l'user
        $cart = Cart::factory()->create([
            'id'      => 10,
            'user_id' => $user->id,
        ]);

        // Mock du service
        $mockService = Mockery::mock(PaymentService::class);

        // 👇 On assigne manuellement les attributs pour que uuid ne soit pas null
        $fakePayment = new Payment();
        $fakePayment->uuid           = 'uuid-test';
        $fakePayment->status         = PaymentStatus::Pending;
        $fakePayment->transaction_id = null;
        $fakePayment->client_secret  = 'secret-123';

        $mockService
            ->shouldReceive('createFromCart')
            ->once()
            ->with($user->id, $cart->id, 'stripe')
            ->andReturn($fakePayment);

        $this->app->instance(PaymentService::class, $mockService);

        $payload = [
            'cart_id'        => $cart->id,
            'payment_method' => 'stripe',
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/payments', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.uuid', 'uuid-test')
            ->assertJsonPath('data.client_secret', 'secret-123');
    }

    public function testShowStatusReturnsStatusAndPaidAtForOwner()
    {
        $user    = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id'  => $user->id,
            'status'   => PaymentStatus::Paid,
            'paid_at'  => now()->subHour(),
        ]);

        // Owner can fetch
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/payments/{$payment->uuid}")
            ->assertStatus(200)
            ->assertJsonStructure(['status','paid_at'])
            ->assertJsonPath('status', 'paid');

        // Other user gets 404
        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')
            ->getJson("/api/payments/{$payment->uuid}")
            ->assertStatus(404);
    }

    public function testRefundRouteDeletesOldInvoiceAndDispatchesEvent()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // On fixe amount à 100 pour que refunded_amount (20) soit < amount
        $payment = Payment::factory()->create([
            'amount'         => 100.00,
            'invoice_link'   => 'invoice-foo.pdf',
            'refunded_amount'=> 20.00,
            'status'         => PaymentStatus::Paid,
        ]);

        // Mock PaymentService->refundByUuid
        $mockService = Mockery::mock(PaymentService::class);
        $updated = tap($payment, fn($p) => $p->update([
            'refunded_amount' => 50.00,
            'status'          => PaymentStatus::Refunded,
            'refunded_at'     => now(),
        ]));
        $mockService->shouldReceive('refundByUuid')
                    ->once()
                    ->with($payment->uuid, 30.00)
                    ->andReturn($updated);
        $this->app->instance(PaymentService::class, $mockService);

        Storage::fake('invoices');
        // Place un fichier factice
        Storage::disk('invoices')->put('invoice-foo.pdf', 'dummy');

        Event::fake([InvoiceRequested::class]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/payments/{$payment->uuid}/refund", ['amount' => 30.00])
            ->assertStatus(200)
            ->assertJsonPath('refunded_amount', '50.00')
            ->assertJsonPath('status', 'refunded');

        // Le fichier a été supprimé
        Storage::disk('invoices')->assertMissing('invoice-foo.pdf');
        // L’événement a bien été dispatché
        Event::assertDispatched(InvoiceRequested::class, fn($e) => $e->payment->is($updated));
    }

    public function testRefundEndpointReturns404WhenPaymentUuidNotFound()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $invalidUuid = Str::uuid()->toString();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/payments/{$invalidUuid}/refund", ['amount' => 10.00])
            ->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found',
                'code'    => 'not_found',
            ]);
    }

    public function testRefundEndpointValidatesAmountMaximumBasedOnRemainingRefund()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // Créé un paiement de 100 € dont 30 € ont déjà été remboursés
        $payment = Payment::factory()->create([
            'uuid'           => Str::uuid()->toString(),
            'amount'         => 100.00,
            'refunded_amount'=> 30.00,
            'status'         => PaymentStatus::Paid,
        ]);

        // Il reste 70 € remboursables, on tente d’en rembourser 80 €
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/payments/{$payment->uuid}/refund", ['amount' => 80.00])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonFragment([
                'message' => 'The given data was invalid',
            ])
            ->assertJsonFragment([
                 'You can only refund up to 70.', // message() construit via max_refund = 70
            ]);
    }
}
