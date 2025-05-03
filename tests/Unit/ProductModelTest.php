<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\Ticket;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\User;
use App\Models\Payment;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function testProductsTableHasExpectedColumns(): void
    {
        $columns = ['id', 'name', 'product_details', 'price', 'sale', 'stock_quantity', 'created_at', 'updated_at'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('products', $column),
                "Colonne `{$column}` manquante dans `products`."
            );
        }
    }

    public function testProductHasManyCartItems(): void
    {
        $product = Product::factory()->create();
        $cart = Cart::factory()->create();
        $cartItem1 = CartItem::factory()->create(['product_id' => $product->id, 'cart_id' => $cart->id]);
        $cartItem2 = CartItem::factory()->create(['product_id' => $product->id, 'cart_id' => $cart->id]);

        $this->assertCount(2, $product->cartItems);
        $this->assertTrue($product->cartItems->contains($cartItem1));
        $this->assertTrue($product->cartItems->contains($cartItem2));
    }

    public function testProductHasManyTickets(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();
        $payment = Payment::factory()->create(['user_id' => $user->id]);
        $ticket1 = Ticket::factory()->create(['product_id' => $product->id, 'payment_id' => $payment->id, 'user_id' => $user->id]);
        $ticket2 = Ticket::factory()->create(['product_id' => $product->id, 'payment_id' => $payment->id, 'user_id' => $user->id]);

        $this->assertCount(2, $product->tickets);
        $this->assertTrue($product->tickets->contains($ticket1));
        $this->assertTrue($product->tickets->contains($ticket2));
    }
}
