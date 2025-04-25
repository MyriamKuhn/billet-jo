<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use App\Models\CartItem;
use App\Models\Cart;
use App\Models\Product;

class CartItemTableTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if the cart_items table has the expected columns.
     *
     * @return void
     */
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

    /**
     * Test if the cart_items table has the expected foreign keys.
     *
     * @return void
     */
    public function testCartItemBelongsToCartAndProduct(): void
    {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        $item = CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id]);

        $this->assertEquals($cart->id, $item->cart->id);
    }
}

