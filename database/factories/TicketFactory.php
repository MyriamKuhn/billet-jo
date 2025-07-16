<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Enums\TicketStatus;
use Illuminate\Support\Carbon;

/**
 * Factory for creating Ticket instances.
 *
 * This factory generates random ticket data, including product snapshots,
 * user associations, payment details, and ticket status.
 */
class TicketFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
        ]);
        $product = Product::factory()->create();

        $snapshot = [
            'product_name'    => $product->name,
            'ticket_type'     => data_get($product->product_details, 'category', $this->faker->word()),
            'unit_price'      => (float) $product->price,
            'discount_rate'   => (float) $product->sale,
            'discounted_price'=> round($product->price * (1 - $product->sale), 2),
        ];

        return [
            'product_snapshot' => $snapshot,
            'token'            => (string) Str::uuid(),
            'qr_filename'      => 'qr_' . $this->faker->uuid() . '.png',
            'pdf_filename'     => 'ticket_' . $this->faker->uuid() . '.pdf',
            'status'           => TicketStatus::Issued->value,
            'used_at'          => null,
            'refunded_at'      => null,
            'cancelled_at'     => null,
            'user_id'          => $user->id,
            'payment_id'       => $payment->id,
            'product_id'       => $product->id,
        ];
    }

    /**
     * Indicate that the ticket is used.
     *
     * @return static
     */
    public function used(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status'  => TicketStatus::Used->value,
                'used_at' => Carbon::now(),
            ];
        });
    }
}
