<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\PendingPurchaseSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class VisitorInstallmentPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_uses_variant_plan_priority_and_never_browser_amounts(): void
    {
        [$product, $variant, $otherVariant, $plans] = $this->productWithPlans();

        $this->postJson(route('products.purchase-preview', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['variant-6']->id, 'total_amount' => 1])
            ->assertOk()->assertJsonPath('variant_price', 999.99)->assertJsonPath('plan_id', $plans['variant-6']->id)
            ->assertJsonPath('financing_fee', 20)->assertJsonPath('future_payment_count', 5)
            ->assertJsonCount(5, 'future_installments');

        // The 6-payment plan that belongs only to the first variant cannot be reused after changing variants.
        $this->postJson(route('products.purchase-preview', $product), ['variant_id' => $otherVariant->id, 'plan_id' => $plans['variant-6']->id])
            ->assertUnprocessable()->assertJsonPath('changed', true);
    }

    public function test_confirm_revalidates_stock_and_active_plan(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();
        $variant->update(['stock_quantity' => 0]);

        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id])
            ->assertUnprocessable()->assertJsonPath('changed', true)
            ->assertJsonPath('message', 'Stock changed: this variant is now out of stock.');
    }

    public function test_guest_confirmation_preserves_a_server_side_purchase_for_authentication(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();

        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id, 'total_amount' => 1])
            ->assertOk()->assertJsonPath('requires_auth', true)->assertJsonPath('auth_url', route('customer.login'));

        $pending = PendingPurchaseSession::firstOrFail();
        $this->assertSame($product->id, $pending->product_id);
        $this->assertSame($variant->id, $pending->product_variant_id);
        $this->assertSame([$variant->optionValues->first()->id, $variant->optionValues->last()->id], $pending->option_value_ids);
        $this->assertNotSame(1.0, (float) $pending->total_amount);
        $this->assertTrue($pending->expires_at->isFuture());
    }

    public function test_sign_in_returns_customer_to_their_revalidated_checkout(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();
        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id]);
        $user = User::factory()->customer()->create();

        $this->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('checkout.show'));
        $this->get(route('checkout.show'))->assertOk()->assertSee($product->name)->assertSee('Checkout');
    }

    public function test_expired_pending_purchase_is_not_resumed_after_authentication(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();
        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id]);
        PendingPurchaseSession::firstOrFail()->update(['expires_at' => now()->subMinute()]);

        $this->actingAs(User::factory()->customer()->create())->get(route('checkout.show'))
            ->assertRedirect(route('catalog.index'))->assertSessionHas('error', 'Your saved purchase session has expired. Please select your installment plan again.');
    }

    public function test_changed_stock_and_unavailable_plans_stop_the_continuation(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();
        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id]);
        $variant->update(['stock_quantity' => 0]);
        $this->actingAs(User::factory()->customer()->create())->get(route('checkout.show'))
            ->assertRedirect(route('catalog.index'))->assertSessionHas('error', 'Stock changed: this variant is now out of stock.');

        // A new saved selection must also reject a plan that became inactive during sign-in.
        $variant->update(['stock_quantity' => 2]);
        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id]);
        $plans['product-3']->update(['is_active' => false]);
        $this->get(route('checkout.show'))->assertRedirect(route('catalog.index'))
            ->assertSessionHas('error', 'Plan availability changed. Please select an available installment plan.');
    }

    public function test_changed_prices_recalculate_the_checkout_and_show_a_clear_message(): void
    {
        [$product, $variant, , $plans] = $this->productWithPlans();
        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id]);
        $variant->update(['price' => 1200]);

        $this->actingAs(User::factory()->customer()->create())->get(route('checkout.show'))
            ->assertOk()->assertSee('Your price or payment schedule changed while you signed in.')
            ->assertSee('1,200.00');
    }

    public function test_payment_dates_remain_anchored_to_the_confirmed_selection(): void
    {
        Date::setTestNow('2026-08-05 10:00:00');
        [$product, $variant, , $plans] = $this->productWithPlans();
        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plans['product-3']->id]);
        $pending = PendingPurchaseSession::firstOrFail();
        $pending->update(['expires_at' => now()->addMonth()]);

        Date::setTestNow('2026-08-06 10:00:00');
        $this->actingAs(User::factory()->customer()->create())->get(route('checkout.show'))
            ->assertOk()->assertSee('2026-09-05');
        $this->assertSame('2026-08-05', $pending->fresh()->scheduled_at->toDateString());
        Date::setTestNow();
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
            'product-3' => InstallmentPlan::factory()->create(['product_id' => $product->id, 'number_of_payments' => 3, 'down_payment' => 100, 'financing_fee' => 0]),
            'product-9' => InstallmentPlan::factory()->create(['product_id' => $product->id, 'number_of_payments' => 9, 'down_payment' => 0, 'financing_fee' => 0]),
            'variant-6' => InstallmentPlan::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'number_of_payments' => 6, 'down_payment' => 100, 'financing_fee' => 20]),
        ];

        return [$product, $variant, $other, $plans];
    }
}
