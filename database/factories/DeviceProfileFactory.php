<?php

namespace Database\Factories;

use App\Models\DeviceProfile;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceProfileFactory extends Factory
{
    protected $model = DeviceProfile::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->device(),
            'model_identifier' => fake()->unique()->bothify('MODEL-####??'),
            'model_family' => fake()->words(2, true),
            'release_year' => fake()->numberBetween(2020, 2026),
            'connector_type' => 'USB-C',
            'charging_standards' => ['Qi'],
            'features' => ['wireless charging'],
            'metadata' => null,
        ];
    }
}
