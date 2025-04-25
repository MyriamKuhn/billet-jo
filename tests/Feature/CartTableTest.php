<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\Cart;
use App\Models\User;
use App\Models\CartItem;
use App\Models\Product;

class CartTableTest extends TestCase
{
    use RefreshDatabase;

    public function testCartsTableHasExpectedColumns(): void
    {
        $columns = ['id', 'created_at', 'updated_at', 'user_id'];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('carts', $column),
                "Colonne `{$column}` manquante dans `carts`."
            );
        }
    }

    /**
     * Test if the cart belongs to a user.
     *
     * @return void
     */
    public function testCartBelongsToUser(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $cart->user->id);
    }

    /**
     * Test if the cart has many cart items.
     *
     * @return void
     */
    public function testCartHasManyCartItems(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        $item1 = CartItem::factory()->create(['cart_id' => $cart->id, 'product_id'=> $product->id]);
        $item2 = CartItem::factory()->create(['cart_id' => $cart->id, 'product_id'=> $product->id]);

        $this->assertCount(2, $cart->cartItems);
        $this->assertTrue($cart->cartItems->contains($item1));
        $this->assertTrue($cart->cartItems->contains($item2));
    }
}

