<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'specifications' => [
                'display' => fake()->randomElement(['6.1 OLED', '6.7 AMOLED']),
                'chip' => fake()->randomElement(['A18', 'Snapdragon 8 Gen 4']),
            ],
            'brand' => fake()->randomElement(['Apple', 'Samsung', 'Google']),
            'status' => ProductStatus::Active,
            'is_featured' => false,
            'published_at' => now(),
        ];
    }
}
