<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductTranslation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTranslation>
 */
class ProductTranslationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'language' => 'en',
            'name' => strtoupper(fake()->word()),
            'type_name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'benefits' => [fake()->sentence()],
            'materials' => [['part' => 'Frame', 'material' => 'Particleboard']],
            'care_instructions' => [['type' => 'Wipe clean', 'texts' => ['Wipe clean with a damp cloth.']]],
            'safety_information' => null,
            'technical_details' => null,
            'measurements' => [['type' => 'Width', 'text' => '80 cm']],
            'packages' => [['quantity' => 1, 'weight' => '30 kg']],
        ];
    }
}
