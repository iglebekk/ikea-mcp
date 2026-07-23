<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockStatus>
 */
class StockStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'market' => 'us',
            'store_id' => (string) fake()->numberBetween(100, 700),
            'store_name' => fake()->city(),
            'quantity' => fake()->numberBetween(0, 100),
            'probability' => fake()->randomElement(['HIGH_IN_STOCK', 'LOW_IN_STOCK', 'OUT_OF_STOCK']),
            'checked_at' => now(),
        ];
    }
}
