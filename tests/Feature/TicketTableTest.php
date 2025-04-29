<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Product;
use App\Models\Payment;

class TicketTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if the tickets table has the expected columns.
     *
     * @return void
     */
    public function testTicketsTableHasExpectedColumns(): void
    {
        $columns = ['id', 'qr_code_link', 'pdf_link', 'is_used', 'is_refunded', 'created_at', 'updated_at', 'user_id', 'payment_id', 'product_id'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('tickets', $column),
                "Colonne `{$column}` manquante dans `tickets`."
            );
        }
    }

    /**
     * Test if the tickets table has the expected foreign keys.
     *
     * @return void
     */
    public function testTicketBelongsToUserPaymentAndProduct(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create();
        $product = Product::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'payment_id' => $payment->id, 'product_id' => $product->id]);

        $this->assertEquals($user->id, $ticket->user->id);
    }

    public function testTicketBelongsToUser()
    {
        // Création d'un utilisateur et d'un ticket
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        // Vérification que la relation fonctionne
        $this->assertInstanceOf(User::class, $ticket->user);
        $this->assertEquals($user->id, $ticket->user->id);
    }

    public function testTicketBelongsToPayment()
    {
        // Création d'un paiement et d'un ticket
        $payment = Payment::factory()->create();
        $ticket = Ticket::factory()->create(['payment_id' => $payment->id]);

        // Vérification que la relation fonctionne
        $this->assertInstanceOf(Payment::class, $ticket->payment);
        $this->assertEquals($payment->id, $ticket->payment->id);
    }

    public function testTicketBelongsToProduct()
    {
        // Création d'un produit et d'un ticket
        $product = Product::factory()->create();
        $ticket = Ticket::factory()->create(['product_id' => $product->id]);

        // Vérification que la relation fonctionne
        $this->assertInstanceOf(Product::class, $ticket->product);
        $this->assertEquals($product->id, $ticket->product->id);
    }

    public function testTicketCreationWithValidRelations()
    {
        // Création d'un utilisateur, d'un paiement et d'un produit
        $user = User::factory()->create();
        $payment = Payment::factory()->create();
        $product = Product::factory()->create();

        // Création d'un ticket lié à l'utilisateur, au paiement et au produit
        $ticket = Ticket::create([
            'qr_code_link' => 'some-link',
            'pdf_link' => 'some-pdf-link',
            'is_used' => false,
            'is_refunded' => false,
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'product_id' => $product->id,
        ]);

        // Vérification que le ticket a bien les bonnes relations
        $this->assertEquals($user->id, $ticket->user_id);
        $this->assertEquals($payment->id, $ticket->payment_id);
        $this->assertEquals($product->id, $ticket->product_id);
    }

    public function testTicketFillableFields()
    {
        // Création d'un utilisateur, d'un paiement et d'un produit
        $user = User::factory()->create();
        $payment = Payment::factory()->create();
        $product = Product::factory()->create();

        // Création d'un ticket avec des données valides
        $ticket = Ticket::create([
            'qr_code_link' => 'valid-qr-code',
            'pdf_link' => 'valid-pdf-link',
            'is_used' => false,
            'is_refunded' => false,
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'product_id' => $product->id,
        ]);

        // Vérification des valeurs
        $this->assertEquals('valid-qr-code', $ticket->qr_code_link);
        $this->assertEquals('valid-pdf-link', $ticket->pdf_link);
        $this->assertFalse($ticket->is_used);
        $this->assertFalse($ticket->is_refunded);
    }

    public function testTicketBooleanCasting()
    {
        // Création d'un ticket
        $ticket = Ticket::factory()->create([
            'is_used' => '1', // Passé comme chaîne
            'is_refunded' => '0', // Passé comme chaîne
        ]);

        // Vérification des valeurs castées
        $this->assertTrue($ticket->is_used);
        $this->assertFalse($ticket->is_refunded);
    }
}

