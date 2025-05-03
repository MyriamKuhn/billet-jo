<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\Payment;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Product;

class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    public function testPaymentsTableHasExpectedColumns(): void
    {
        $columns = ['id', 'uuid', 'amount', 'payment_method', 'status', 'transaction_id', 'paid_at', 'created_at', 'updated_at', 'user_id'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('payments', $column),
                "Colonne `{$column}` manquante dans `payments`."
            );
        }
    }

    public function testPaymentBelongsToUser(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $payment->user->id);
    }

    public function testPaymentHasManyTickets()
    {
        $payment = Payment::factory()->create();
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $ticket1 = Ticket::factory()->create(['payment_id' => $payment->id, 'user_id' => $user->id, 'product_id' => $product->id]);
        $ticket2 = Ticket::factory()->create(['payment_id' => $payment->id, 'user_id' => $user->id, 'product_id' => $product->id]);

        $this->assertCount(2, $payment->tickets);
        $this->assertTrue($payment->tickets->contains($ticket1));
        $this->assertTrue($payment->tickets->contains($ticket2));
    }
}

