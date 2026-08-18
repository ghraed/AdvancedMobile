<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorInstallmentPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_revalidates_stock_and_active_plan(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();
        $variant->update(['stock_quantity' => 0]);

        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id])
            ->assertUnprocessable()->assertJsonPath('changed', true)
            ->assertJsonPath('message', 'Stock changed: this variant is now out of stock.');
    }

    public function test_confirmation_directs_the_visitor_to_the_preselected_document_application(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();

        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id, 'total_amount' => 1])
            ->assertOk()->assertJsonPath('confirmed', true)
            ->assertJsonPath('application_url', route('installments.create', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'installment_months' => 3,
            ]));

        $this->assertDatabaseCount('pending_purchase_sessions', 0);
    }

    private function productWithPlans(): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Active]);
        $storage = ProductOption::factory()->create(['product_id' => $product->id, 'slug' => 'storage']);
        $color = ProductOption::factory()->create(['product_id' => $product->id, 'slug' => 'color']);
        $s128 = ProductOptionValue::factory()->create(['product_option_id' => $storage->id, 'name' => '128 GB', 'display_name' => '128 GB']);
        $s256 = ProductOptionValue::factory()->create(['product_option_id' => $storage->id, 'name' => '256 GB', 'display_name' => '256 GB']);
        $black = ProductOptionValue::factory()->create(['product_option_id' => $color->id, 'name' => 'Black', 'display_name' => 'Black']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 999.99, 'stock_quantity' => 2, 'option_signature' => ProductVariant::buildOptionSignature([$s128->id, $black->id])]);
        $variant->optionValues()->sync([$s128->id, $black->id]);
        $other = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1199.99, 'stock_quantity' => 2, 'option_signature' => ProductVariant::buildOptionSignature([$s256->id, $black->id])]);
        $other->optionValues()->sync([$s256->id, $black->id]);
        $plans = [
            'product-3' => InstallmentPlan::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'number_of_payments' => 3, 'down_payment' => 100, 'financing_fee' => 0]),
            'product-9' => InstallmentPlan::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'number_of_payments' => 9, 'down_payment' => 0, 'financing_fee' => 0]),
            'variant-6' => InstallmentPlan::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'number_of_payments' => 6, 'down_payment' => 100, 'financing_fee' => 20]),
        ];

        return [$product, $variant, $other, $plans];
    }
}
