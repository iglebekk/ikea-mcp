<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'related_item_no' => fake()->unique()->numerify('########'),
            'variant_group' => fake()->word(),
            'variant_attributes' => ['color' => fake()->safeColorName()],
        ];
    }
}
