<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Payment;
use App\Models\Product;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qr_code_link' => $this->faker->url,
            'pdf_link' => $this->faker->url,
            'is_used' => false,
            'is_refunded' => false,
            'user_id' => User::factory(),
            'payment_id' => Payment::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
