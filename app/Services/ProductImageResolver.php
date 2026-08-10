<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class ProductImageResolver
{
    /** Exact-variant override, selected color gallery, then general gallery. */
    public function resolve(Product $product, ProductVariant $variant): Collection
    {
        $variantImages = $variant->relationLoaded('images') ? $variant->images : $variant->images()->get();
        if ($variantImages->isNotEmpty()) return $variantImages;

        $colorId = $variant->relationLoaded('optionValues') ? $variant->colorValue()?->id : $variant->colorOption?->id;
        if ($colorId) {
            $colorImages = $product->relationLoaded('images')
                ? $product->images->where('product_option_value_id', $colorId)->values()
                : $product->images()->where('product_option_value_id', $colorId)->get();
            if ($colorImages->isNotEmpty()) return $colorImages;
        }

        return $product->relationLoaded('images')
            ? $product->images->whereNull('product_option_value_id')->values()
            : $product->images()->whereNull('product_option_value_id')->get();
    }
}
