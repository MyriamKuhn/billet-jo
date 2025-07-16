<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating ProductTranslation instances.
 *
 * This factory generates random product translation data, including locale,
 * name, and product details such as description and features.
 */
class ProductTranslationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProductTranslation::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
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
