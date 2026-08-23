<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_product_with_category_options_variants_images_and_plans(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Phones']);
        $category = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Android Phones']);

        $payload = $this->validPayload($category->id);
        $payload['variants'][0]['barcode'] = '629100000101';
        $payload['variants'][0]['cost_price'] = '650.25';
        $response = $this->actingAs($admin)->post(route('admin.products.store'), $payload);

        $product = Product::query()->with(['productOptions.values', 'variants.optionValues', 'images', 'installmentPlans'])->firstOrFail();

        $response->assertRedirect(route('admin.products.edit', $product));
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertCount(2, $product->productOptions);
        $this->assertSame(4, $product->variants->count());
        $this->assertSame('629100000101', $product->variants->firstWhere('sku', 'PIX-128-BLK')->barcode);
        $this->assertSame(65025, $product->variants->firstWhere('sku', 'PIX-128-BLK')->cost_price_cents);
        $this->assertSame(['Display', 'Battery'], collect($product->specifications)->pluck('key')->all());
        $this->assertCount(1, $product->images);
        $this->assertCount(12, $product->installmentPlans);
        $this->assertSame(4, $product->installmentPlans->where('number_of_payments', 3)->count());
        Storage::disk('public')->assertExists($product->images->first()->image_path);
    }

    public function test_installment_plans_reject_duplicate_active_scope_and_foreign_variant_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $otherProduct = Product::factory()->create();
        $foreignVariant = ProductVariant::factory()->create(['product_id' => $otherProduct->id]);

        $payload = $this->validPayload($category->id);
        $payload['installment_plans'][] = [
            'scope' => 'variant',
            'variant_key' => 'variant-128-black',
            'months' => 3,
            'total_amount' => 900,
            'is_active' => 1,
        ];
        $payload['installment_plans'][] = [
            'scope' => 'variant',
            'months' => 9,
            'total_amount' => 900,
            'product_variant_id' => $foreignVariant->id,
            'variant_key' => 'foreign',
            'is_active' => 1,
        ];

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), $payload)
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors([
                'installment_plans.12.months',
                'installment_plans.13.product_variant_id',
            ]);
    }

    public function test_product_edit_page_lists_hierarchical_categories_and_preview_link(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Phones']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Flagships', 'is_active' => false]);
        $product = Product::factory()->create(['category_id' => $child->id]);

        $this->actingAs($admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSeeText('Phones (Parent)')
            ->assertSeeText('— Flagships [Inactive]')
            ->assertSee(route('admin.products.preview', $product), false);
    }

    public function test_admin_can_preview_installment_schedule_from_backend(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.products.installment-preview.create'), [
            'scope' => 'variant',
            'number_of_payments' => 3,
            'total_amount' => 1020,
            'interval_type' => 'monthly',
            'variants' => [
                ['client_key' => 'variant-1', 'sku' => 'PIX-128-BLK', 'price' => 1000],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('preview.amount_due_now', 340)
            ->assertJsonPath('preview.future_payment_count', 2)
            ->assertJsonPath('preview.total_amount', 1020)
            ->assertJsonPath('preview.future_installments.1.amount', 340);
    }

    public function test_variant_payload_must_have_unique_skus_and_unique_option_combinations(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $payload = $this->validPayload($category->id);
        $payload['variants'][1]['sku'] = $payload['variants'][0]['sku'];
        $payload['variants'][1]['option_values'] = $payload['variants'][0]['option_values'];

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), $payload)
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors([
                'variants.1.sku',
                'variants.1.option_values',
            ]);
    }

    public function test_variant_barcodes_must_be_unique_when_present(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $payload = $this->validPayload($category->id);
        $payload['variants'][0]['barcode'] = 'DUPLICATE-BARCODE';
        $payload['variants'][1]['barcode'] = 'DUPLICATE-BARCODE';

        $this->actingAs($admin)
            ->from(route('admin.products.create'))
            ->post(route('admin.products.store'), $payload)
            ->assertRedirect(route('admin.products.create'))
            ->assertSessionHasErrors('variants.1.barcode');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_update_stock_and_status_and_preserve_retired_stocked_variants_as_inactive(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $product = $this->createExistingProduct($category);

        $variant = $product->variants()->with('optionValues.productOption')->orderBy('id')->firstOrFail();
        $remainingVariant = $product->variants()->with('optionValues.productOption')->orderByDesc('id')->firstOrFail();

        $payload = $this->payloadFromProduct($product);
        $payload['status'] = ProductStatus::Draft->value;
        $payload['confirm_variant_retirement'] = 1;
        $payload['variants'] = [
            [
                'id' => $remainingVariant->id,
                'sku' => 'PIX-256-BLK',
                'price' => 999.00,
                'compare_at_price' => 1099.00,
                'stock_quantity' => 7,
                'is_active' => 1,
                'option_values' => $remainingVariant->optionValues->map(fn ($value) => [
                    'id' => $value->id,
                    'option_slug' => $value->productOption->slug,
                    'name' => $value->name,
                ])->values()->all(),
                'images' => [],
            ],
        ];

        $response = $this->actingAs($admin)->put(route('admin.products.update', $product), $payload);

        $response->assertRedirect(route('admin.products.edit', $product));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::Draft->value]);
        $this->assertDatabaseHas('product_variants', ['id' => $remainingVariant->id, 'stock_quantity' => 7, 'is_active' => true]);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'is_active' => false]);
        $this->assertNotEmpty(session('warnings'));
    }

    public function test_admin_can_toggle_product_status_from_index_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['status' => ProductStatus::Draft]);

        $this->actingAs($admin)
            ->patch(route('admin.products.activate', $product))
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::Active->value]);

        $this->actingAs($admin)
            ->patch(route('admin.products.deactivate', $product))
            ->assertRedirect();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::Draft->value]);
    }

    public function test_admin_preview_renders_inactive_product_without_public_route_access(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $product = $this->createExistingProduct($category, ProductStatus::Draft);

        $this->actingAs($admin)
            ->get(route('admin.products.preview', $product))
            ->assertOk()
            ->assertSeeText($product->name);

        $this->get(route('products.show', $product))->assertNotFound();
    }

    public function test_stocked_products_cannot_be_deleted_from_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->createExistingProduct(Category::factory()->create());

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    protected function validPayload(int $categoryId): array
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
                $this->variantRow('PIX-256-GRY', 999.00, 1099.00, 0, '256 GB', 'Grey', 'variant-256-grey'),
            ],
            'installment_plans' => collect([
                ['key' => 'variant-128-black', 'total' => 899.00], ['key' => 'variant-128-grey', 'total' => 899.00],
                ['key' => 'variant-256-black', 'total' => 999.00], ['key' => 'variant-256-grey', 'total' => 999.00],
            ])->flatMap(fn (array $variant) => collect([3, 6, 9])->map(fn (int $months) => [
                'scope' => 'variant', 'variant_key' => $variant['key'], 'months' => $months,
                'total_amount' => $variant['total'] + ($months === 6 ? 60 : ($months === 9 ? 120 : 0)), 'is_active' => 1,
            ]))->values()->all(),
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

    protected function createExistingProduct(Category $category, ProductStatus $status = ProductStatus::Active): Product
    {
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => $status,
            'name' => 'Pixel Existing',
            'slug' => 'pixel-existing',
        ]);

        $storage = ProductOption::factory()->create([
            'product_id' => $product->id,
            'name' => 'Storage',
            'slug' => ProductOption::STORAGE_SLUG,
        ]);
        $color = ProductOption::factory()->create([
            'product_id' => $product->id,
            'name' => 'Color',
            'slug' => ProductOption::COLOR_SLUG,
        ]);

        $storage128 = ProductOptionValue::factory()->create(['product_option_id' => $storage->id, 'name' => '128 GB']);
        $storage256 = ProductOptionValue::factory()->create(['product_option_id' => $storage->id, 'name' => '256 GB']);
        $black = ProductOptionValue::factory()->create(['product_option_id' => $color->id, 'name' => 'Black']);

        $variantOne = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PIX-128-BLK',
            'price' => 899.00,
            'stock_quantity' => 4,
            'is_active' => true,
            'option_signature' => ProductVariant::buildOptionSignature([$storage128->id, $black->id]),
        ]);
        $variantOne->optionValues()->sync([$storage128->id, $black->id]);

        $variantTwo = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'PIX-256-BLK',
            'price' => 999.00,
            'stock_quantity' => 2,
            'is_active' => true,
            'option_signature' => ProductVariant::buildOptionSignature([$storage256->id, $black->id]),
        ]);
        $variantTwo->optionValues()->sync([$storage256->id, $black->id]);

        return $product->fresh([
            'productOptions.values',
            'variants.optionValues.productOption',
            'installmentPlans',
            'images',
        ]);
    }

    protected function payloadFromProduct(Product $product): array
    {
        $product->load(['productOptions.values', 'variants.optionValues.productOption', 'images', 'installmentPlans']);

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
                    'display_name' => $value->display_name ?? $value->name,
                    'hex_value' => $value->hex_value,
                    'sort_order' => $value->sort_order,
                    'is_active' => $value->is_active,
                ])->values()->all(),
            ])->values()->all(),
            'product_images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'alt_text' => $image->alt_text,
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
            ])->values()->all(),
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'client_key' => (string) $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
                'stock_quantity' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
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
