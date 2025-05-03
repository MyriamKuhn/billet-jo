<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\Product;

class CartItemModelTest extends TestCase
{
    use RefreshDatabase;

    public function testCartItemsTableHasExpectedColumns(): void
    {
        $columns = ['id', 'quantity', 'created_at', 'updated_at', 'cart_id', 'product_id', ];

        foreach ($columns as $column) {
            $this->assertTrue(
                Schema::hasColumn('cart_items', $column),
                "Colonne `{$column}` manquante dans `cart_items`."
            );
        }
    }

    public function testCartItemBelongsToCartAndProduct(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        $item = CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

        $this->assertEquals($cart->id, $item->cart->id);
    }

    public function testCartItemQuantityIsCastToInteger()
    {
        $cartItem = CartItem::factory()->create([
            'quantity' => '5' // Passé en tant que chaîne
        ]);

        $this->assertIsInt($cartItem->quantity);
        $this->assertEquals(5, $cartItem->quantity);
    }

    public function testCartItemCreationWithValidData()
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();

        $cartItem = CartItem::create([
            'quantity' => 1,
            'cart_id' => $cart->id,
            'product_id' => $product->id
        ]);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
    }

    public function testCartItemCreationWithInvalidData()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        CartItem::create([
            'quantity' => 1,
            'cart_id' => null, // Invalid cart_id
            'product_id' => null, // Invalid product_id
        ]);
    }

    public function testCartItemBelongsToProduct()
    {
        $cartItem = CartItem::factory()->create();

        $this->assertInstanceOf(Product::class, $cartItem->product);
        $this->assertEquals($cartItem->product->id, $cartItem->product_id);
    }
}

