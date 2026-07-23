<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'market' => 'us',
            'language' => 'en',
            'ikea_id' => fake()->unique()->numerify('#####'),
            'name' => fake()->words(2, true),
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}
