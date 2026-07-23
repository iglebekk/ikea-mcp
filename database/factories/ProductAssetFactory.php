<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAsset>
 */
class ProductAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => 'image',
            'url' => 'https://www.ikea.com/images/'.fake()->uuid().'.jpg',
            'title' => fake()->words(3, true),
            'sort' => 0,
        ];
    }

    public function document(): static
    {
        return $this->state(['type' => 'assembly_instruction', 'url' => 'https://www.ikea.com/manuals/'.fake()->uuid().'.pdf']);
    }
}
