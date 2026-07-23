<?php

namespace Database\Factories;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country' => fake()->unique()->countryCode(),
            'name' => fake()->country(),
            'languages' => ['en'],
            'currency' => fake()->currencyCode(),
            'is_active' => true,
        ];
    }
}
