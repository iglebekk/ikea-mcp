<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'item_no' => fake()->unique()->numerify('########'),
            'product_type' => fake()->randomElement(['bookcase', 'chair', 'table', 'sofa']),
            'series' => fake()->randomElement(['BILLY', 'POÄNG', 'KALLAX', 'MALM']),
            'first_observed_at' => now(),
            'last_observed_at' => now(),
        ];
    }
}
