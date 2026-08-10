<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\PendingPurchaseSession;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_immutable_order_and_installment_schedule(): void
    {
        [$product, $variant, $plan] = $this->purchasable();
        $user = User::factory()->customer()->create();
        $this->savePurchase($product, $variant, $plan, $user);

        $this->actingAs($user)->post(route('checkout.store'))->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertSame($product->name, $order->product_name);
        $this->assertSame($variant->sku, $order->sku);
        $this->assertSame('128 GB', $order->storage);
        $this->assertSame('Black', $order->color);
        $this->assertSame(3, $order->installments()->count());
        $this->assertSame(1, $order->installments()->first()->sequence);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
        $this->assertNotNull(PendingPurchaseSession::firstOrFail()->completed_at);
    }

    public function test_duplicate_submission_creates_only_one_order(): void
    {
        [$product, $variant, $plan] = $this->purchasable();
        $user = User::factory()->customer()->create();
        $this->savePurchase($product, $variant, $plan, $user);
        $this->actingAs($user)->post(route('checkout.store'));
        $this->post(route('checkout.store'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
    }

    public function test_insufficient_stock_creates_no_order(): void
    {
        [$product, $variant, $plan] = $this->purchasable();
        $user = User::factory()->customer()->create();
        $this->savePurchase($product, $variant, $plan, $user);
        $variant->update(['stock_quantity' => 0]);

        $this->actingAs($user)->post(route('checkout.store'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull(PendingPurchaseSession::firstOrFail()->completed_at);
    }

    public function test_later_price_and_plan_changes_do_not_alter_order_snapshots(): void
    {
        [$product, $variant, $plan] = $this->purchasable();
        $user = User::factory()->customer()->create();
        $this->savePurchase($product, $variant, $plan, $user);
        $this->actingAs($user)->post(route('checkout.store'));
        $order = Order::with('installments')->firstOrFail();
        $schedule = $order->installments->map(fn ($i) => [$i->sequence, (string) $i->amount, $i->due_date->toDateString()])->all();

        $variant->update(['price' => 2500]);
        $plan->update(['number_of_payments' => 6, 'down_payment' => 500, 'financing_fee' => 99]);
        $order->refresh()->load('installments');
        $this->assertSame('1000.00', (string) $order->variant_price);
        $this->assertSame($schedule, $order->installments->map(fn ($i) => [$i->sequence, (string) $i->amount, $i->due_date->toDateString()])->all());
    }

    public function test_a_failure_rolls_back_order_stock_and_pending_completion(): void
    {
        [$product, $variant, $plan] = $this->purchasable();
        $user = User::factory()->customer()->create();
        $token = $this->savePurchase($product, $variant, $plan, $user);
        Event::listen('eloquent.creating: '.Order::class, fn () => throw new RuntimeException('forced failure'));

        try { app(OrderCreationService::class)->create($user, $token); } catch (RuntimeException) {}
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $variant->fresh()->stock_quantity);
        $this->assertNull(PendingPurchaseSession::firstOrFail()->completed_at);
    }

    private function savePurchase(Product $product, ProductVariant $variant, InstallmentPlan $plan, User $user): string
    {
        $this->postJson(route('products.confirm-purchase', $product), ['variant_id' => $variant->id, 'plan_id' => $plan->id]);
        $token = session('pending_purchase_token');
        PendingPurchaseSession::firstOrFail()->update(['user_id' => $user->id]);
        return $token;
    }

    private function purchasable(): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => ProductStatus::Active, 'name' => 'Snapshot Phone']);
        $storage = ProductOption::factory()->create(['product_id' => $product->id, 'slug' => 'storage']);
        $color = ProductOption::factory()->create(['product_id' => $product->id, 'slug' => 'color']);
        $s128 = ProductOptionValue::factory()->create(['product_option_id' => $storage->id, 'name' => '128 GB', 'display_name' => '128 GB']);
        $black = ProductOptionValue::factory()->create(['product_option_id' => $color->id, 'name' => 'Black', 'display_name' => 'Black']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SNAP-128-BLK', 'price' => 1000, 'stock_quantity' => 2, 'option_signature' => ProductVariant::buildOptionSignature([$s128->id, $black->id])]);
        $variant->optionValues()->sync([$s128->id, $black->id]);
        $plan = InstallmentPlan::factory()->create(['product_id' => $product->id, 'number_of_payments' => 3, 'down_payment' => 100, 'financing_fee' => 20]);
        return [$product, $variant, $plan];
    }
}
