<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductTranslationFactory extends Factory
{
    protected $model = ProductTranslation::class;

    public function definition()
    {
        $locale = $this->faker->randomElement(['en', 'fr', 'de']);

        return [
            'product_id'      => Product::factory(),

            'locale'          => $locale,
            'name'            => $this->faker->words(3, true),
            'product_details' => [
                'description' => $this->faker->sentence(),
                'features'    => $this->faker->randomElements(['lightweight', 'waterproof', 'eco-friendly'], 2),
            ],
        ];
    }
}
