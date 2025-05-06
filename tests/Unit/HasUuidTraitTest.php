<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HasUuidTraitTest extends TestCase
{
    use RefreshDatabase;

    public function testItGeneratesAUuidWhenCreatingAPayment()
    {
        // Créer un utilisateur pour l'association avec le paiement
        $user = User::factory()->create();

        // Créer un paiement
        $payment = Payment::create([
            'invoice_link' => 'http://example.com/invoice/12345',
            'amount' => 100.00,
            'payment_method' => 'stripe',
            'status' => 'paid',
            'transaction_id' => 'abc123xyz',
            'paid_at' => now(),
            'user_id' => $user->id,
        ]);

        // Vérifier que l'UUID est généré
        $this->assertNotNull($payment->uuid);
        $this->assertTrue(Str::isUuid($payment->uuid)); // Vérifier que l'UUID est valide
    }
}
