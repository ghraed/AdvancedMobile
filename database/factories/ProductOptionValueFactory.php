<?php

namespace Database\Factories;

use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductOptionValueFactory extends Factory
{
    protected $model = ProductOptionValue::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['128 GB', '256 GB', 'Black', 'Grey']);

        return [
            'product_option_id' => ProductOption::factory(),
            'name' => $name,
            'slug' => fn (array $attributes) => Str::slug($attributes['name']),
            'display_name' => fn (array $attributes) => $attributes['name'],
            'hex_value' => str($name)->lower()->contains(['black', 'grey']) ? fake()->randomElement(['#111111', '#8A8F98']) : null,
            'swatch_image' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
