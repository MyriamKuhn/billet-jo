<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Cart;
use App\Models\Product;

/**
 * Factory for creating CartItem instances.
 * This factory generates random cart item data, including quantity,
 * associated cart ID, and product ID.
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quantity' => $this->faker->numberBetween(1, 10),
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
