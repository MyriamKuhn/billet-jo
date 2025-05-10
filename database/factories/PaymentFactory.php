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
    protected $model = Payment::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']);

        // Build a random cart snapshot
        $items = [];
        $count = $this->faker->numberBetween(1, 3);
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'ticket_type' => $this->faker->randomElement(['adult', 'child', 'senior']),
                'quantity'    => $this->faker->numberBetween(1, 5),
                'unit_price'  => $this->faker->randomFloat(2, 10, 500),
            ];
        }

        // Calculate total amount
        $amount = collect($items)
            ->reduce(fn($sum, $item) => $sum + ($item['quantity'] * $item['unit_price']), 0);

        return [
            'uuid'           => $this->faker->uuid,
            'invoice_link'   => $this->faker->unique()->url,
            'cart_snapshot'  => $items,
            'amount'         => $amount,
            'payment_method' => $this->faker->randomElement(['paypal', 'stripe', 'free']),
            'status'         => $status,
            'transaction_id' => $status === 'paid' ? $this->faker->uuid : null,
            'client_secret'  => $status === 'pending' ? $this->faker->sha1 : null,
            'paid_at'        => $status === 'paid'
                                    ? $this->faker->dateTimeBetween('-1 week', 'now')
                                    : null,
            'refunded_at'    => $status === 'refunded'
                                    ? $this->faker->dateTimeBetween('-1 week', 'now')
                                    : null,
            'refunded_amount'=> $status === 'refunded'
                                    ? $this->faker->randomFloat(2, 1, $amount)
                                    : null,
            'user_id'        => User::factory(),
        ];
    }
}

