<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductOptionFactory extends Factory
{
    protected $model = ProductOption::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Storage', 'Color']);

        return [
            'product_id' => Product::factory(),
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
