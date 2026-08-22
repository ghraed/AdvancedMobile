<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_configured_storage_and_color_pair_resolves_by_option_value_ids(): void
    {
        [$product, $storage, $colors, $variants] = $this->productWithVariants();

        foreach ($variants as $key => $variant) {
            [$storageName, $colorName] = explode(':', $key);
            $response = $this->postJson(route('products.resolve-variant', $product), [
                'option_value_ids' => [$storage[$storageName]->id, $colors[$colorName]->id],
            ]);

            $response->assertOk()->assertJsonPath('resolved', true)->assertJsonPath('variant_id', $variant->id);
        }
    }

    public function test_missing_storage_color_pair_is_not_silently_substituted(): void
    {
        [$product, $storage, $colors] = $this->productWithVariants(includeMissingPair: true);

        $this->postJson(route('products.resolve-variant', $product), [
            'option_value_ids' => [$storage['128 GB']->id, $colors['Silver']->id],
        ])->assertOk()->assertJsonPath('resolved', false)
            ->assertJsonPath('message', 'This option combination is unavailable.');
    }

    public function test_out_of_stock_variant_resolves_but_cannot_be_purchased(): void
    {
        [$product, $storage, $colors, $variants] = $this->productWithVariants();
        $outOfStock = $variants['256 GB:Black'];
        $outOfStock->update(['stock_quantity' => 0]);

        $this->postJson(route('products.resolve-variant', $product), [
            'option_value_ids' => [$storage['256 GB']->id, $colors['Black']->id],
        ])->assertOk()->assertJsonPath('resolved', true)->assertJsonPath('variant_id', $outOfStock->id)
            ->assertJsonPath('in_stock', false)->assertJsonPath('stock_message', 'Out of stock.');
    }

    public function test_variant_images_override_color_galleries_which_fall_back_to_general_images(): void
    {
        [$product, $storage, $colors, $variants] = $this->productWithVariants();
        ProductImage::factory()->create(['product_id' => $product->id, 'image_path' => 'products/general.webp', 'alt_text' => 'General image']);
        ProductImage::factory()->create(['product_id' => $product->id, 'product_option_value_id' => $colors['Black']->id, 'image_path' => 'products/black-gallery.webp', 'alt_text' => 'Black gallery image']);
        ProductImage::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variants['128 GB:Black']->id, 'image_path' => 'products/black.webp', 'alt_text' => 'Black image']);

        $this->postJson(route('products.resolve-variant', $product), ['option_value_ids' => [$storage['128 GB']->id, $colors['Black']->id]])
            ->assertJsonPath('images.0.alt', 'Black image')->assertJsonPath('images.0.url', asset('storage/products/black.webp'));
        $this->postJson(route('products.resolve-variant', $product), ['option_value_ids' => [$storage['256 GB']->id, $colors['Silver']->id]])
            ->assertJsonPath('images.0.alt', 'General image')->assertJsonPath('images.0.url', asset('storage/products/general.webp'));
        $this->postJson(route('products.resolve-variant', $product), ['option_value_ids' => [$storage['256 GB']->id, $colors['Black']->id]])
            ->assertJsonPath('images.0.alt', 'Black gallery image')->assertJsonPath('images.0.url', asset('storage/products/black-gallery.webp'));
    }

    public function test_product_page_includes_selectors_and_similar_product_suggestions(): void
    {
        [$product] = $this->productWithVariants();

        $this->get(route('products.show', $product))->assertOk()
            ->assertSeeText('Storage')->assertSeeText('Color')->assertSeeText('Installment plans')
            ->assertSeeText('Continue to purchase')->assertSeeText('Similar products')
            ->assertSeeText('Share on WhatsApp')->assertSee('https://wa.me/?text=', false)
            ->assertDontSee(config('app.url').'/products/'.$product->slug.'/resolve-variant', false);
    }

    public function test_optionless_variant_can_be_resolved_without_browser_supplied_option_values(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'option_signature' => '', 'stock_quantity' => 1]);

        $this->postJson(route('products.resolve-variant', $product), ['option_value_ids' => []])
            ->assertOk()->assertJsonPath('resolved', true)->assertJsonPath('variant_id', $variant->id);
    }

    private function productWithVariants(bool $includeMissingPair = false): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Active]);
        $storageOption = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Storage', 'slug' => 'storage']);
        $colorOption = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color', 'slug' => 'color']);
        $storage = collect(['128 GB', '256 GB'])->mapWithKeys(fn ($name) => [$name => ProductOptionValue::factory()->create(['product_option_id' => $storageOption->id, 'name' => $name, 'display_name' => $name])]);
        $colors = collect(['Black' => '#111111', 'Silver' => '#aaaaaa'])->mapWithKeys(fn ($hex, $name) => [$name => ProductOptionValue::factory()->create(['product_option_id' => $colorOption->id, 'name' => $name, 'display_name' => $name, 'hex_value' => $hex])]);
        $pairs = $includeMissingPair ? [['128 GB', 'Black'], ['256 GB', 'Black']] : [['128 GB', 'Black'], ['128 GB', 'Silver'], ['256 GB', 'Black'], ['256 GB', 'Silver']];
        $variants = collect($pairs)->mapWithKeys(function ($pair, $index) use ($product, $storage, $colors) {
            [$storageName, $colorName] = $pair;
            $ids = [$storage[$storageName]->id, $colors[$colorName]->id];
            $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SKU-'.$index.'-'.$storageName.'-'.$colorName, 'price' => 500 + $index * 100, 'stock_quantity' => 4, 'option_signature' => ProductVariant::buildOptionSignature($ids)]);
            $variant->optionValues()->sync($ids);
            return [implode(':', $pair) => $variant];
        });
        InstallmentPlan::factory()->create(['product_id' => $product->id, 'number_of_payments' => 3, 'installment_amount' => 1, 'total_amount' => 1]);
        $related = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $related->id, 'stock_quantity' => 1]);

        return [$product, $storage, $colors, $variants];
    }
}
