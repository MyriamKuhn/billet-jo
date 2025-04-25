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
}

