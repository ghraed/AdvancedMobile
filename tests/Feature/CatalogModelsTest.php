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
use App\Services\InstallmentPlanService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_relationships_and_self_parent_guard(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->first()->is($child));

        $this->expectException(DomainException::class);

        $parent->parent_id = $parent->id;
        $parent->save();
    }

    public function test_visible_in_menu_scope_includes_ancestors_with_sellable_descendants(): void
    {
        $root = Category::factory()->create(['name' => 'Phones', 'sort_order' => 1]);
        $child = Category::factory()->create(['parent_id' => $root->id, 'name' => 'Android', 'sort_order' => 1]);
        $hidden = Category::factory()->create(['name' => 'Accessories', 'sort_order' => 2]);

        $sellableProduct = Product::factory()->create([
            'category_id' => $child->id,
            'status' => ProductStatus::Active,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $sellableProduct->id,
            'stock_quantity' => 5,
            'is_active' => true,
            'option_signature' => '1|2',
        ]);

        Product::factory()->create([
            'category_id' => $hidden->id,
            'status' => ProductStatus::Draft,
        ]);

        $visible = Category::query()
            ->whereIn('id', [$root->id, $child->id, $hidden->id])
            ->visibleInMenu()
            ->pluck('id')
            ->all();

        $this->assertSame([$root->id, $child->id], $visible);
    }

    public function test_empty_categories_are_hidden_from_the_visitor_menu(): void
    {
        $emptyCategory = Category::factory()->create([
            'name' => 'Empty',
            'is_active' => true,
        ]);

        $visible = Category::query()->whereKey($emptyCategory->id)->visibleInMenu()->exists();
        $state = Category::resolveMenuStateMap(
            Category::query()->with('products.variants')->whereKey($emptyCategory->id)->get()
        );

        $this->assertFalse($visible);
        $this->assertSame('empty', $state[$emptyCategory->id]);
    }

    public function test_categories_become_visible_after_an_active_in_stock_product_is_assigned(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::Active,
        ]);

        $this->assertFalse(Category::query()->whereKey($category->id)->visibleInMenu()->exists());

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'stock_quantity' => 3,
            'option_signature' => '201|301',
        ]);

        $this->assertTrue(Category::query()->whereKey($category->id)->visibleInMenu()->exists());
    }

    public function test_categories_become_hidden_when_their_last_available_product_is_deactivated_or_out_of_stock(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::Active,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'stock_quantity' => 2,
            'option_signature' => '202|302',
        ]);

        $this->assertTrue(Category::query()->whereKey($category->id)->visibleInMenu()->exists());

        $variant->update(['stock_quantity' => 0]);

        $this->assertFalse(Category::query()->whereKey($category->id)->visibleInMenu()->exists());

        $variant->update(['stock_quantity' => 4, 'is_active' => true]);
        $product->update(['status' => ProductStatus::Draft]);

        $this->assertFalse(Category::query()->whereKey($category->id)->visibleInMenu()->exists());
    }

    public function test_parent_categories_appear_when_a_child_category_contains_an_available_product(): void
    {
        $parent = Category::factory()->create(['is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $child->id,
            'status' => ProductStatus::Active,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'stock_quantity' => 1,
            'option_signature' => '203|303',
        ]);

        $visible = Category::query()->whereKey([$parent->id, $child->id])->visibleInMenu()->pluck('id')->all();

        $this->assertSame([$parent->id, $child->id], $visible);
    }

    public function test_circular_parent_relationships_are_rejected(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->assertContains($child->id, $parent->descendantIds());
    }

    public function test_publicly_available_scope_requires_active_product_active_category_and_stocked_variant(): void
    {
        $activeCategory = Category::factory()->create(['is_active' => true]);
        $inactiveCategory = Category::factory()->create(['is_active' => false]);

        $publicProduct = Product::factory()->create([
            'category_id' => $activeCategory->id,
            'status' => ProductStatus::Active,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $publicProduct->id,
            'stock_quantity' => 2,
            'is_active' => true,
            'option_signature' => '10|20',
        ]);

        $draftProduct = Product::factory()->create([
            'category_id' => $activeCategory->id,
            'status' => ProductStatus::Draft,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $draftProduct->id,
            'stock_quantity' => 5,
            'is_active' => true,
            'option_signature' => '11|21',
        ]);

        $inactiveCategoryProduct = Product::factory()->create([
            'category_id' => $inactiveCategory->id,
            'status' => ProductStatus::Active,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $inactiveCategoryProduct->id,
            'stock_quantity' => 5,
            'is_active' => true,
            'option_signature' => '12|22',
        ]);

        $outOfStockProduct = Product::factory()->create([
            'category_id' => $activeCategory->id,
            'status' => ProductStatus::Active,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $outOfStockProduct->id,
            'stock_quantity' => 0,
            'is_active' => true,
            'option_signature' => '13|23',
        ]);

        $this->assertSame(
            [$publicProduct->id],
            Product::query()->publiclyAvailable()->pluck('id')->all()
        );
    }

    public function test_duplicate_variant_option_signatures_are_rejected_per_product(): void
    {
        $product = Product::factory()->create();

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'option_signature' => '100|200',
        ]);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'option_signature' => '100|200',
        ]);
    }

    public function test_variant_helpers_return_storage_and_color_values(): void
    {
        $product = Product::factory()->create();
        $storageOption = ProductOption::factory()->create([
            'product_id' => $product->id,
            'name' => 'Storage',
            'slug' => ProductOption::STORAGE_SLUG,
        ]);
        $colorOption = ProductOption::factory()->create([
            'product_id' => $product->id,
            'name' => 'Color',
            'slug' => ProductOption::COLOR_SLUG,
        ]);

        $storage = ProductOptionValue::factory()->create([
            'product_option_id' => $storageOption->id,
            'name' => '256 GB',
        ]);
        $color = ProductOptionValue::factory()->create([
            'product_option_id' => $colorOption->id,
            'name' => 'Grey',
            'hex_value' => '#8A8F98',
        ]);

        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'option_signature' => ProductVariant::buildOptionSignature([$storage->id, $color->id]),
        ]);
        $variant->optionValues()->sync([$storage->id, $color->id]);
        $variant->load('optionValues.productOption');

        $this->assertSame('256 GB', $variant->storageValue()?->name);
        $this->assertSame('Grey', $variant->colorValue()?->name);
    }

    public function test_primary_images_are_unique_per_product_or_variant_context(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $productPrimary = ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'is_primary' => true,
        ]);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'is_primary' => true,
        ]);

        $variantPrimary = ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'is_primary' => true,
        ]);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'is_primary' => true,
        ]);

        $this->assertFalse($productPrimary->fresh()->is_primary);
        $this->assertFalse($variantPrimary->fresh()->is_primary);
        $this->assertSame(1, ProductImage::query()->where('product_id', $product->id)->whereNull('product_variant_id')->where('is_primary', true)->count());
        $this->assertSame(1, ProductImage::query()->where('product_variant_id', $variant->id)->where('is_primary', true)->count());
    }

    public function test_variant_specific_installment_plan_takes_priority_and_rounding_is_safe(): void
    {
        $service = app(InstallmentPlanService::class);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 1000,
        ]);

        $generalPlan = $service->calculatePlan(1000, 3, 100, 0, 'monthly');
        InstallmentPlan::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'number_of_payments' => 3,
            'variant_key' => null,
            'down_payment' => $generalPlan['down_payment'],
            'financing_fee' => $generalPlan['financing_fee'],
            'installment_amount' => $generalPlan['installment_amount'],
            'total_amount' => $generalPlan['total_amount'],
        ]);

        $variantPlan = $service->calculatePlan(1000, 3, 100, 20, 'monthly');
        $expected = InstallmentPlan::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_key' => (string) $variant->id,
            'number_of_payments' => 3,
            'down_payment' => $variantPlan['down_payment'],
            'financing_fee' => $variantPlan['financing_fee'],
            'installment_amount' => $variantPlan['installment_amount'],
            'total_amount' => $variantPlan['total_amount'],
        ]);

        $resolved = $service->resolvePlanForProduct($product, $variant, 3);
        $rounding = $service->calculatePlan(1000, 3, 0, 0, 'monthly');

        $this->assertTrue($resolved->is($expected));
        $this->assertSame([500.00, 500.00], $rounding['schedule']);
        $this->assertSame(2, $rounding['future_payment_count']);
        $this->assertSame(1000.00, $rounding['amount_due_now'] + array_sum($rounding['schedule']));
    }

    public function test_installment_schedule_handles_due_dates_and_rounding_for_common_plan_lengths(): void
    {
        $service = app(InstallmentPlanService::class);
        $startingAt = CarbonImmutable::parse('2026-01-31');

        $three = $service->calculatePlan(1000, 3, 100, 20, 'monthly', $startingAt);
        $six = $service->calculatePlan(1200, 6, 0, 0, 'monthly', $startingAt);
        $nine = $service->calculatePlan(999.99, 9, 0, 0, 'monthly', $startingAt);

        $this->assertSame(100.00, $three['amount_due_now']);
        $this->assertSame([460.00, 460.00], $three['schedule']);
        $this->assertSame(['2026-02-28', '2026-03-31'], array_column($three['future_installments'], 'due_date'));

        $this->assertCount(5, $six['future_installments']);
        $this->assertSame(1200.00, $six['amount_due_now'] + array_sum($six['schedule']));

        $this->assertCount(8, $nine['future_installments']);
        $this->assertSame(999.99, round($nine['amount_due_now'] + array_sum($nine['schedule']), 2));
        $this->assertSame(125.00, $nine['installment_amount']);
        $this->assertSame(124.99, $nine['final_installment_amount']);
    }
}
