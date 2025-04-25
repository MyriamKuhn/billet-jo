<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Payment;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']);

        return [
            'uuid' => $this->faker->uuid,
            'invoice_link' => $this->faker->unique()->url,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'payment_method' => $this->faker->randomElement(['paypal', 'stripe']),
            'status' => $status,
            'transaction_id' => $status === 'paid' ? $this->faker->uuid : null,
            'paid_at' => $status === 'paid' ? $this->faker->dateTimeBetween('-1 week', 'now') : null,
            'user_id' => User::factory(),
        ];
    }
}
