<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PosCheckoutService;
use App\Services\PosRefundService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class PosSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_access_is_protected_by_dedicated_authorization(): void
    {
        $this->get(route('admin.pos.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->customer()->create())->get(route('admin.pos.index'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.pos.index'))->assertOk()->assertSeeText('Point of Sale');
    }

    public function test_search_finds_sellable_variants_by_name_brand_sku_and_barcode(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant(['name' => 'Galaxy Ultra', 'brand' => 'Samsung'], ['sku' => 'GAL-ULTRA-BLK', 'barcode' => '629100000001']);

        foreach (['Galaxy', 'Samsung', 'GAL-ULTRA', '629100000001'] as $term) {
            $this->actingAs($admin)->getJson(route('admin.pos.products.search', ['q' => $term]))
                ->assertOk()
                ->assertJsonPath('data.0.variant_id', $variant->id)
                ->assertJsonPath('data.0.stock_quantity', 10);
        }
    }

    public function test_search_excludes_inactive_products_variants_categories_options_and_zero_stock(): void
    {
        $admin = User::factory()->admin()->create();
        [$activeProduct, $active] = $this->sellableVariant(['name' => 'Visible Phone'], ['sku' => 'VISIBLE']);
        [, $inactiveVariant] = $this->sellableVariant(['name' => 'Hidden Variant'], ['sku' => 'HIDDEN-V', 'is_active' => false]);
        [, $zeroStock] = $this->sellableVariant(['name' => 'Zero Stock'], ['sku' => 'ZERO', 'stock_quantity' => 0]);
        [$inactiveProduct, $inactiveProductVariant] = $this->sellableVariant(['name' => 'Inactive Product'], ['sku' => 'HIDDEN-P']);
        $inactiveProduct->update(['status' => ProductStatus::Draft]);
        [$inactiveCategoryProduct, $inactiveCategoryVariant] = $this->sellableVariant(['name' => 'Inactive Category'], ['sku' => 'HIDDEN-C']);
        $inactiveCategoryProduct->category->update(['is_active' => false]);
        $option = ProductOption::factory()->create(['product_id' => $activeProduct->id, 'is_active' => false]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id]);
        $active->optionValues()->sync([$value->id]);

        $ids = collect($this->actingAs($admin)->getJson(route('admin.pos.products.search'))->json('data'))->pluck('variant_id');
        $this->assertFalse($ids->contains($active->id));
        $this->assertFalse($ids->contains($inactiveVariant->id));
        $this->assertFalse($ids->contains($zeroStock->id));
        $this->assertFalse($ids->contains($inactiveProductVariant->id));
        $this->assertFalse($ids->contains($inactiveCategoryVariant->id));
    }

    public function test_barcode_is_unique_nullable_and_editable_through_product_validation(): void
    {
        [, $first] = $this->sellableVariant([], ['barcode' => 'ABC-UNIQUE']);
        [, $second] = $this->sellableVariant([], ['barcode' => null]);
        $this->assertNull($second->barcode);
        $this->expectException(UniqueConstraintViolationException::class);
        $second->update(['barcode' => $first->barcode]);
    }

    public function test_one_item_checkout_uses_database_price_and_decreases_stock(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 123.45, 'stock_quantity' => 5]);
        $payload = $this->payload([$variant->id => 2], 24690);
        $payload['items'][0]['unit_price_cents'] = 1;

        $response = $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $payload)->assertOk();
        $sale = Order::findOrFail($response->json('order.id'));

        $this->assertSame(24690, $sale->subtotal_cents);
        $this->assertSame(24690, $sale->total_cents);
        $this->assertSame(2, $sale->items()->firstOrFail()->quantity);
        $this->assertSame(12345, $sale->items()->firstOrFail()->unit_price_cents);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
    }

    public function test_multi_item_checkout_has_correct_totals_and_snapshots(): void
    {
        $admin = User::factory()->admin()->create();
        [, $one] = $this->sellableVariant(['name' => 'Phone A'], ['sku' => 'A', 'price' => 10]);
        [, $two] = $this->sellableVariant(['name' => 'Phone B'], ['sku' => 'B', 'price' => 25]);

        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $this->payload([$one->id => 2, $two->id => 3], 9500))->assertOk();
        $sale = Order::with('items')->firstOrFail();
        $this->assertSame(9500, $sale->total_cents);
        $this->assertSame(5, $sale->quantity);
        $this->assertSame(['A', 'B'], $sale->items->pluck('sku')->sort()->values()->all());
    }

    public function test_fixed_and_percentage_discounts_are_calculated_server_side(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 100, 'stock_quantity' => 5]);
        $fixed = $this->payload([$variant->id => 1], 9000);
        $fixed['discount'] = ['type' => 'fixed', 'value' => 1000];
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $fixed)->assertOk();

        $percent = $this->payload([$variant->id => 1], 8500);
        $percent['discount'] = ['type' => 'percentage', 'value' => 15];
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $percent)->assertOk();

        $this->assertSame([1000, 1500], Order::orderBy('id')->pluck('discount_cents')->all());
        $this->assertSame([9000, 8500], Order::orderBy('id')->pluck('total_cents')->all());
    }

    public function test_invalid_discounts_are_rejected_without_creating_a_sale(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10]);
        $over = $this->payload([$variant->id => 1], 0);
        $over['discount'] = ['type' => 'fixed', 'value' => 1001];
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $over)->assertUnprocessable();
        $percentage = $this->payload([$variant->id => 1], 0);
        $percentage['discount'] = ['type' => 'percentage', 'value' => 101];
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $percentage)->assertJsonValidationErrors('discount.value');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cash_card_and_mixed_payments_are_stored_separately(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 100, 'stock_quantity' => 5]);
        $cash = $this->payload([$variant->id => 1], 10000);
        $cash['payments'][0]['cash_received_cents'] = 12000;
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $cash)->assertOk();

        $card = $this->payload([$variant->id => 1], 10000, 'card');
        $card['payments'][0]['reference'] = 'CARD-1';
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $card)->assertOk();

        $mixed = $this->payload([$variant->id => 1], 10000);
        $mixed['payments'] = [
            ['method' => 'cash', 'amount_cents' => 3000, 'cash_received_cents' => 5000],
            ['method' => 'card', 'amount_cents' => 7000, 'reference' => 'MIX-1'],
        ];
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $mixed)->assertOk();

        $this->assertDatabaseHas('order_payments', ['payment_method' => 'cash', 'amount_cents' => 10000, 'cash_received_cents' => 12000, 'change_due_cents' => 2000]);
        $this->assertDatabaseHas('order_payments', ['payment_method' => 'card', 'amount_cents' => 10000, 'reference' => 'CARD-1']);
        $this->assertCount(2, Order::latest('id')->firstOrFail()->payments);
    }

    public function test_underpayment_overpayment_and_invalid_mixed_payments_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 100]);
        foreach ([9999, 10001] as $amount) {
            $payload = $this->payload([$variant->id => 1], $amount);
            $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $payload)->assertUnprocessable();
        }
        $mixed = $this->payload([$variant->id => 1], 10000);
        $mixed['payments'] = [['method' => 'cash', 'amount_cents' => 5000], ['method' => 'cash', 'amount_cents' => 5000]];
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $mixed)->assertJsonValidationErrors('payments');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    public function test_checkout_rejects_insufficient_stock_and_rechecks_inactive_state(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10, 'stock_quantity' => 1]);
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $this->payload([$variant->id => 2], 2000))->assertUnprocessable();
        $variant->update(['is_active' => false]);
        $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $this->payload([$variant->id => 1], 1000))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
    }

    public function test_payment_failure_rolls_back_order_lines_and_stock(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10, 'stock_quantity' => 2]);
        Event::listen('eloquent.creating: '.OrderPayment::class, fn () => throw new RuntimeException('forced payment failure'));

        try {
            app(PosCheckoutService::class)->checkout($admin, $this->payload([$variant->id => 1], 1000));
        } catch (RuntimeException) {
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(2, $variant->fresh()->stock_quantity);
    }

    public function test_order_line_failure_leaves_no_partial_sale_or_stock_change(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10, 'stock_quantity' => 2]);
        Event::listen('eloquent.creating: '.OrderItem::class, fn () => throw new RuntimeException('forced line failure'));

        try {
            app(PosCheckoutService::class)->checkout($admin, $this->payload([$variant->id => 1], 1000));
        } catch (RuntimeException) {
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertSame(2, $variant->fresh()->stock_quantity);
    }

    public function test_idempotency_returns_original_sale_and_decrements_stock_once(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10, 'stock_quantity' => 3]);
        $payload = $this->payload([$variant->id => 2], 2000);
        $first = $this->actingAs($admin)->postJson(route('admin.pos.checkout'), $payload)->assertOk()->json('order.id');
        $second = $this->postJson(route('admin.pos.checkout'), $payload)->assertOk()->json('order.id');
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
    }

    public function test_refund_restores_stock_once_creates_immutable_record_and_marks_payments(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10, 'stock_quantity' => 5]);
        $sale = app(PosCheckoutService::class)->checkout($admin, $this->payload([$variant->id => 2], 2000));

        $this->actingAs($admin)->post(route('admin.pos.sales.refund', $sale), ['reason' => 'Customer returned unopened items'])->assertRedirect();
        $this->assertSame(5, $variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('orders', ['id' => $sale->id, 'status' => 'refunded', 'payment_status' => 'refunded']);
        $refund = OrderRefund::firstOrFail();
        $this->assertSame(2000, $refund->amount_cents);
        $this->assertSame(2, $refund->restored_items[0]['quantity']);
        $this->expectException(LogicException::class);
        $refund->update(['reason' => 'changed']);
    }

    public function test_double_and_unauthorized_refunds_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10, 'stock_quantity' => 3]);
        $sale = app(PosCheckoutService::class)->checkout($admin, $this->payload([$variant->id => 1], 1000));
        $this->actingAs(User::factory()->customer()->create())->post(route('admin.pos.sales.refund', $sale), ['reason' => 'No access'])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.pos.sales.refund', $sale), ['reason' => 'Valid return'])->assertRedirect();
        $this->postJson(route('admin.pos.sales.refund', $sale), ['reason' => 'Again'])->assertUnprocessable();
        $this->assertDatabaseCount('order_refunds', 1);
        $this->assertSame(3, $variant->fresh()->stock_quantity);
    }

    public function test_refund_failure_rolls_back_stock_order_and_payment_status(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant([], ['price' => 10, 'stock_quantity' => 3]);
        $sale = app(PosCheckoutService::class)->checkout($admin, $this->payload([$variant->id => 1], 1000));
        Event::listen('eloquent.creating: '.OrderRefund::class, fn () => throw new RuntimeException('forced refund failure'));
        try {
            app(PosRefundService::class)->refund($sale, $admin, 'Failed refund');
        } catch (RuntimeException) {
        }
        $this->assertSame(2, $variant->fresh()->stock_quantity);
        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame('completed', $sale->payments()->firstOrFail()->status);
        $this->assertDatabaseCount('order_refunds', 0);
    }

    public function test_receipt_is_authorized_and_uses_historical_snapshots(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Till Admin']);
        [$product, $variant] = $this->sellableVariant(['name' => 'Snapshot Phone'], ['sku' => 'SNAP-SKU', 'price' => 88]);
        $sale = app(PosCheckoutService::class)->checkout($admin, $this->payload([$variant->id => 1], 8800));
        $product->update(['name' => 'Renamed Phone']);
        $variant->update(['sku' => 'NEW-SKU', 'price' => 999]);

        $this->actingAs($admin)->get(route('admin.pos.sales.receipt', $sale))->assertOk()->assertSeeText('Snapshot Phone')->assertSeeText('SNAP-SKU')->assertSeeText('Till Admin')->assertDontSeeText('Renamed Phone');
        $this->actingAs(User::factory()->customer()->create())->get(route('admin.pos.sales.receipt', $sale))->assertForbidden();
    }

    public function test_sales_history_filters_pos_sales_and_paginates(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->sellableVariant(['name' => 'Filter Phone'], ['sku' => 'FILTER-SKU', 'price' => 1, 'stock_quantity' => 30]);
        $first = null;
        for ($i = 0; $i < 21; $i++) {
            $payload = $this->payload([$variant->id => 1], 100, $i === 0 ? 'card' : 'cash');
            $sale = app(PosCheckoutService::class)->checkout($admin, $payload);
            $first ??= $sale;
        }

        $this->actingAs($admin)->get(route('admin.pos.sales.index'))->assertOk()->assertSeeText('POS Sales')->assertSee('page=2', false);
        $this->get(route('admin.pos.sales.index', ['reference' => $first->reference, 'payment_method' => 'card', 'product' => 'FILTER-SKU', 'cashier_id' => $admin->id, 'status' => 'completed']))
            ->assertOk()->assertSeeText($first->reference);
        $first->update(['sales_channel' => 'online']);
        $this->get(route('admin.pos.sales.index', ['reference' => $first->reference]))->assertOk()->assertDontSeeText($first->reference);
    }

    private function payload(array $variantQuantities, int $paymentCents, string $method = 'cash'): array
    {
        return [
            'idempotency_key' => (string) str()->uuid(),
            'items' => collect($variantQuantities)->map(fn (int $quantity, int $variantId) => ['variant_id' => $variantId, 'quantity' => $quantity, 'unit_price_cents' => 1])->values()->all(),
            'discount' => [],
            'payments' => [['method' => $method, 'amount_cents' => $paymentCents]],
        ];
    }

    private function sellableVariant(array $productOverrides = [], array $variantOverrides = []): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'status' => ProductStatus::Active,
            'name' => 'POS Phone',
            'brand' => 'POS Brand',
        ], $productOverrides));
        $variant = ProductVariant::factory()->create(array_merge([
            'product_id' => $product->id,
            'sku' => 'POS-'.str()->upper(str()->random(8)),
            'price' => 100,
            'stock_quantity' => 10,
            'is_active' => true,
        ], $variantOverrides));

        return [$product->load('category'), $variant];
    }
}
