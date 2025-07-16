<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Product instances.
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            "name"=> $this->faker->word(),
            "product_details"=> [
                'places' => $this->faker->randomElement([1, 2, 4]),
                "description"=> [
                    $this->faker->sentence(10, true),
                    $this->faker->sentences(3, true),
                ],
                "date"=> $this->faker->date(),
                "time"=> $this->faker->time(),
                "location"=> $this->faker->address(),
                "category"=> $this->faker->word(),
                "image" => "https://picsum.photos/seed/{$this->faker->randomNumber(3)}/600/400",
            ],
            "price"=> $this->faker->randomFloat(2, 10, 100),
            "sale" => $this->faker->randomFloat(2, 0, 90) / 100,
            "stock_quantity"=> $this->faker->numberBetween(1, 100),
        ];
    }
}
