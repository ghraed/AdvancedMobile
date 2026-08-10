<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_complete_the_catalog_workflow_and_menu_visibility_tracks_inventory(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create([
            'password' => 'password',
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Phones',
            'slug' => 'phones',
            'description' => 'Parent category',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $parent = Category::query()->where('slug', 'phones')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Android Phones',
            'slug' => 'android-phones',
            'parent_id' => $parent->id,
            'description' => 'Subcategory',
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $category = Category::query()->where('slug', 'android-phones')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->productPayload($category->id))
            ->assertRedirect();

        $product = Product::query()
            ->with(['productOptions.values', 'variants.optionValues.productOption', 'installmentPlans'])
            ->firstOrFail();

        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertSame([3, 6, 9], $product->installmentPlans->sortBy('number_of_payments')->pluck('number_of_payments')->all());
        $this->assertCount(4, $product->variants);

        $this->get(route('elite-mobile-marketplace.home'))
            ->assertOk()
            ->assertSeeText('Phones')
            ->assertSeeText('Android Phones');

        $updatePayload = $this->payloadFromProductWithZeroStock($product);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $updatePayload)
            ->assertRedirect(route('admin.products.edit', $product));

        $this->get(route('elite-mobile-marketplace.home'))
            ->assertOk()
            ->assertDontSeeText('Phones')
            ->assertDontSeeText('Android Phones');
    }

    protected function productPayload(int $categoryId): array
    {
        return [
            'category_id' => $categoryId,
            'name' => 'Pixel 10 Pro',
            'slug' => 'pixel-10-pro',
            'brand' => 'Google',
            'short_description' => 'Flagship Android phone',
            'description' => 'Long-form product description.',
            'status' => ProductStatus::Active->value,
            'is_featured' => 1,
            'published_at' => now()->format('Y-m-d\TH:i'),
            'specifications' => [
                ['key' => 'Display', 'value' => '6.7 inches'],
                ['key' => 'Battery', 'value' => '5000 mAh'],
            ],
            'product_options' => [
                [
                    'name' => 'Storage',
                    'slug' => ProductOption::STORAGE_SLUG,
                    'sort_order' => 0,
                    'is_active' => 1,
                    'values' => [
                        ['name' => '128 GB', 'display_name' => '128 GB', 'sort_order' => 0, 'is_active' => 1],
                        ['name' => '256 GB', 'display_name' => '256 GB', 'sort_order' => 1, 'is_active' => 1],
                    ],
                ],
                [
                    'name' => 'Color',
                    'slug' => ProductOption::COLOR_SLUG,
                    'sort_order' => 1,
                    'is_active' => 1,
                    'values' => [
                        ['name' => 'Black', 'display_name' => 'Black', 'hex_value' => '#111111', 'sort_order' => 0, 'is_active' => 1],
                        ['name' => 'Grey', 'display_name' => 'Grey', 'hex_value' => '#888888', 'sort_order' => 1, 'is_active' => 1],
                    ],
                ],
            ],
            'product_images' => [
                [
                    'upload' => UploadedFile::fake()->image('product-main.jpg'),
                    'alt_text' => 'Pixel front image',
                    'sort_order' => 0,
                    'is_primary' => 1,
                ],
            ],
            'variants' => [
                $this->variantRow('PIX-128-BLK', 899.00, 1049.00, 5, '128 GB', 'Black', 'variant-128-black'),
                $this->variantRow('PIX-128-GRY', 899.00, 1049.00, 2, '128 GB', 'Grey', 'variant-128-grey'),
                $this->variantRow('PIX-256-BLK', 999.00, 1099.00, 3, '256 GB', 'Black', 'variant-256-black'),
                $this->variantRow('PIX-256-GRY', 999.00, 1099.00, 1, '256 GB', 'Grey', 'variant-256-grey'),
            ],
            'installment_plans' => [
                ['scope' => 'product', 'months' => 3, 'down_payment' => 0, 'financing_fee' => 0, 'interval_type' => 'monthly', 'is_active' => 1],
                ['scope' => 'product', 'months' => 6, 'down_payment' => 90, 'financing_fee' => 30, 'interval_type' => 'monthly', 'is_active' => 1],
                ['scope' => 'variant', 'variant_key' => 'variant-128-black', 'months' => 9, 'down_payment' => 99.00, 'financing_fee' => 45.00, 'interval_type' => 'monthly', 'is_active' => 1],
            ],
        ];
    }

    protected function variantRow(string $sku, float $price, float $compareAtPrice, int $stock, string $storage, string $color, string $clientKey): array
    {
        return [
            'client_key' => $clientKey,
            'sku' => $sku,
            'price' => $price,
            'compare_at_price' => $compareAtPrice,
            'stock_quantity' => $stock,
            'is_active' => 1,
            'option_values' => [
                ['option_slug' => ProductOption::STORAGE_SLUG, 'name' => $storage],
                ['option_slug' => ProductOption::COLOR_SLUG, 'name' => $color],
            ],
            'images' => [],
        ];
    }

    protected function payloadFromProductWithZeroStock(Product $product): array
    {
        return [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'status' => $product->status->value,
            'is_featured' => $product->is_featured,
            'published_at' => optional($product->published_at)->format('Y-m-d\TH:i'),
            'specifications' => $product->specifications,
            'product_options' => $product->productOptions->map(fn ($option) => [
                'id' => $option->id,
                'name' => $option->name,
                'slug' => $option->slug,
                'sort_order' => $option->sort_order,
                'is_active' => $option->is_active,
                'values' => $option->values->map(fn ($value) => [
                    'id' => $value->id,
                    'name' => $value->name,
                    'display_name' => $value->display_name,
                    'hex_value' => $value->hex_value,
                    'swatch_image' => $value->swatch_image,
                    'sort_order' => $value->sort_order,
                    'is_active' => $value->is_active,
                ])->values()->all(),
            ])->values()->all(),
            'product_images' => [],
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'client_key' => (string) $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
                'stock_quantity' => 0,
                'is_active' => 1,
                'option_values' => $variant->optionValues->map(fn ($value) => [
                    'id' => $value->id,
                    'option_slug' => $value->productOption->slug,
                    'name' => $value->name,
                ])->values()->all(),
                'images' => [],
            ])->values()->all(),
            'installment_plans' => $product->installmentPlans->map(fn ($plan) => [
                'id' => $plan->id,
                'scope' => $plan->product_variant_id ? 'variant' : 'product',
                'variant_key' => $plan->variant_key ?? (string) $plan->product_variant_id,
                'product_variant_id' => $plan->product_variant_id,
                'months' => $plan->number_of_payments,
                'down_payment' => $plan->down_payment,
                'financing_fee' => $plan->financing_fee,
                'interval_type' => $plan->interval_type,
                'is_active' => $plan->is_active,
            ])->values()->all(),
        ];
    }
}
