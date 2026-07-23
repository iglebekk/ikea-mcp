<?php

namespace Database\Factories;

use App\Models\MarketProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketProduct>
 */
class MarketProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'market' => 'us',
            'currency' => 'USD',
            'price' => fake()->randomFloat(2, 5, 900),
            'regular_price' => null,
            'campaign_price' => null,
            'url' => fake()->url(),
            'status' => 'active',
            'online_sellable' => true,
            'rating_value' => fake()->randomFloat(2, 1, 5),
            'rating_count' => fake()->numberBetween(0, 5000),
            'last_checked_at' => now(),
            'last_changed_at' => now(),
        ];
    }

    public function discontinued(): static
    {
        return $this->state(['status' => 'discontinued']);
    }
}
