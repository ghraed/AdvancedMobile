<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'barcode' => null,
            'price' => fake()->randomFloat(2, 299, 1999),
            'compare_at_price' => null,
            'cost_price_cents' => null,
            'stock_quantity' => fake()->numberBetween(0, 25),
            'is_active' => true,
            'option_signature' => fake()->unique()->numerify('###|###'),
        ];
    }
}
