<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'image_path' => 'products/'.fake()->slug().'.webp',
            'alt_text' => fake()->sentence(4),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }

    public function forVariant(?ProductVariant $variant = null): static
    {
        return $this->state(fn () => [
            'product_id' => $variant?->product_id ?? Product::factory(),
            'product_variant_id' => $variant?->id ?? ProductVariant::factory(),
        ]);
    }
}
