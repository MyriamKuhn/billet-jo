<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Payment;
use App\Models\Product;

/**
 * Factory for creating Payment instances.
 *
 * This factory generates random payment data, including items purchased,
 * their quantities, prices, and payment status.
 */
class PaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']);

        $items = [];
        $count = $this->faker->numberBetween(1, 3);

        for ($i = 0; $i < $count; $i++) {
            $product = Product::factory()->create();

            $quantity  = $this->faker->numberBetween(1, 5);
            $unitPrice = $this->faker->randomFloat(2, 10, 500);

            $discountRate     = $this->faker->randomFloat(2, 0, 0.3); // jusque 30%
            $discountedPrice  = round($unitPrice * (1 - $discountRate), 2);

            $items[] = [
                'product_id'       => $product->id,
                'product_name'     => $product->name,
                'ticket_type'      => $this->faker->randomElement(['adult','child','senior']),
                'ticket_places'    => $quantity,
                'quantity'         => $quantity,
                'unit_price'       => $unitPrice,
                'discount_rate'    => $discountRate,
                'discounted_price' => $discountedPrice,
            ];
        }

        $amount = collect($items)
            ->reduce(fn($sum, $line) => $sum + ($line['quantity'] * $line['discounted_price']), 0);

        return [
            'uuid'           => $this->faker->uuid,
            'invoice_link'   => $this->faker->unique()->url,
            'cart_snapshot'  => [
                'items'  => $items,
                'locale' => config('app.fallback_locale'),
            ],
            'amount'         => $amount,
            'payment_method' => $this->faker->randomElement(['paypal','stripe','free']),
            'status'         => $status,
            'transaction_id' => $status === 'paid'    ? $this->faker->uuid  : null,
            'client_secret'  => $status === 'pending' ? $this->faker->sha1 : null,
            'paid_at'        => $status === 'paid'
                                   ? $this->faker->dateTimeBetween('-1 week','now')
                                   : null,
            'refunded_at'    => $status === 'refunded'
                                   ? $this->faker->dateTimeBetween('-1 week','now')
                                   : null,
            'refunded_amount'=> $status === 'refunded'
                                   ? $this->faker->randomFloat(2, 1, $amount)
                                   : null,
            'user_id'        => User::factory(),
        ];
    }
}
