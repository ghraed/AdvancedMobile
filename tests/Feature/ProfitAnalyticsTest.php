<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PosCheckoutService;
use App\Services\PosRefundService;
use App\Services\ProfitAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class ProfitAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_cost_discount_and_price_are_snapshotted_and_catalog_cost_changes_do_not_rewrite_profit(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->variant(price: 1000, costCents: 70000, stock: 5);
        $sale = $this->sale($admin, $variant, 2, 190000, ['type' => 'fixed', 'value' => 10000]);
        $line = $sale->items->first();

        $this->assertSame(100000, $line->unit_price_cents);
        $this->assertSame(70000, $line->unit_cost_cents);
        $this->assertSame(10000, $line->discount_cents);
        $this->assertSame(190000, $line->total_cents);

        $variant->update(['cost_price_cents' => 75000, 'price' => 1200]);
        $summary = $this->summary();

        $this->assertSame(200000, $summary['revenue_cents']);
        $this->assertSame(10000, $summary['discount_cents']);
        $this->assertSame(190000, $summary['net_sales_cents']);
        $this->assertSame(140000, $summary['cogs_cents']);
        $this->assertSame(50000, $summary['gross_profit_cents']);
        $this->assertSame(26.32, $summary['gross_margin_percent']);
        $this->assertSame(2, $summary['units_sold']);
        $this->assertSame(1, $summary['orders_count']);
        $this->assertSame(190000, $summary['average_order_value_cents']);
    }

    public function test_refunds_reverse_net_sales_units_and_cogs_without_double_counting_the_refund(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->variant(price: 100, costCents: 6000, stock: 5);
        $sale = $this->sale($admin, $variant, 2, 20000);
        app(PosRefundService::class)->refund($sale, $admin, 'Returned in original condition');

        $summary = $this->summary();
        $this->assertSame(20000, $summary['revenue_cents']);
        $this->assertSame(20000, $summary['refund_cents']);
        $this->assertSame(0, $summary['net_sales_cents']);
        $this->assertSame(0, $summary['cogs_cents']);
        $this->assertSame(0, $summary['gross_profit_cents']);
        $this->assertSame(0, $summary['units_sold']);
        $this->assertDatabaseCount('order_refunds', 1);
    }

    public function test_financial_order_item_snapshots_cannot_be_updated(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->variant(price: 100, costCents: 6000, stock: 2);
        $line = $this->sale($admin, $variant, 1, 10000)->items->first();

        $this->expectException(LogicException::class);
        $line->update(['unit_cost_cents' => 1]);
    }

    public function test_cancelled_and_failed_sales_are_excluded_while_completed_paid_sale_is_counted(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->variant(price: 10, costCents: 500, stock: 10);
        $valid = $this->sale($admin, $variant, 1, 1000);
        $cancelled = $this->sale($admin, $variant, 1, 1000);
        $cancelled->update(['status' => 'cancelled']);
        $failed = $this->sale($admin, $variant, 1, 1000);
        $failed->update(['payment_status' => 'failed']);

        $summary = $this->summary();
        $this->assertSame(1000, $summary['net_sales_cents']);
        $this->assertSame(500, $summary['cogs_cents']);
        $this->assertSame(1, $summary['orders_count']);
        $this->assertSame($valid->id, Order::query()->where('status', 'completed')->where('payment_status', 'paid')->value('id'));
    }

    public function test_unknown_cost_is_warned_and_excluded_from_profit_while_negative_profit_is_preserved(): void
    {
        $admin = User::factory()->admin()->create();
        [, $unknown] = $this->variant(price: 100, costCents: null, stock: 2);
        [, $loss] = $this->variant(price: 100, costCents: 12000, stock: 2);
        $this->sale($admin, $unknown, 1, 10000);
        $this->sale($admin, $loss, 1, 10000);

        $summary = $this->summary();
        $this->assertSame(20000, $summary['net_sales_cents']);
        $this->assertSame(10000, $summary['unknown_cost_sales_cents']);
        $this->assertSame(10000, $summary['known_cost_sales_cents']);
        $this->assertSame(12000, $summary['cogs_cents']);
        $this->assertSame(-2000, $summary['gross_profit_cents']);
        $this->assertSame(-20.0, $summary['gross_margin_percent']);

        $report = app(ProfitAnalyticsService::class)->report(['range' => 'last_30_days']);
        $this->assertCount(1, $report['loss_making']);
        $this->assertSame(2000, (int) $report['loss_making']->first()->total_loss_cents);
    }

    public function test_zero_known_revenue_has_a_safe_null_margin(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->variant(price: 0, costCents: 0, stock: 1);
        $this->sale($admin, $variant, 1, 0);

        $summary = $this->summary();
        $this->assertSame(0, $summary['net_sales_cents']);
        $this->assertNull($summary['gross_margin_percent']);
        $this->assertSame(0, $summary['average_order_value_cents']);
    }

    public function test_date_presets_boundaries_and_previous_equivalent_period_comparison_are_correct(): void
    {
        $admin = User::factory()->admin()->create();
        [, $variant] = $this->variant(price: 10, costCents: 500, stock: 5);
        CarbonImmutable::setTestNow('2026-08-23 12:00:00');
        $this->sale($admin, $variant, 1, 1000);
        CarbonImmutable::setTestNow('2026-08-22 12:00:00');
        $this->sale($admin, $variant, 1, 1000);
        CarbonImmutable::setTestNow('2026-08-23 12:00:00');

        $today = app(ProfitAnalyticsService::class)->report(['range' => 'today']);
        $this->assertSame(1, $today['summary']['orders_count']);
        $this->assertSame(1, $today['previous_summary']['orders_count']);
        $this->assertSame(0.0, $today['changes']['revenue']);
        $this->assertSame('hour', $today['trend']['granularity']);

        $custom = app(ProfitAnalyticsService::class)->report(['range' => 'custom', 'date_from' => '2026-08-22', 'date_to' => '2026-08-23']);
        $this->assertSame(2, $custom['summary']['orders_count']);
    }

    public function test_product_variant_brand_channel_payment_cashier_and_nested_category_filters_work(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Phones']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Flagships']);
        [$product, $variant] = $this->variant(price: 10, costCents: 500, stock: 3, category: $child, brand: 'Acme');
        $this->sale($admin, $variant, 1, 1000, [], 'card');

        foreach ([
            ['product_id' => $product->id], ['variant_id' => $variant->id], ['brand' => 'Acme'],
            ['sales_channel' => 'pos'], ['payment_method' => 'card'], ['cashier_id' => $admin->id],
            ['category_id' => $parent->id], ['category_id' => $child->id],
        ] as $filter) {
            $this->assertSame(1, $this->summary($filter)['orders_count']);
        }
        $this->assertSame(0, $this->summary(['brand' => 'Other'])['orders_count']);

        $report = app(ProfitAnalyticsService::class)->report(['range' => 'last_30_days']);
        $categoryNames = $report['categories']->pluck('category_name');
        $this->assertTrue($categoryNames->contains('Phones'));
        $this->assertTrue($categoryNames->contains('Flagships'));
        $this->assertSame(1000, $report['categories']->firstWhere('category_name', 'Phones')['net_sales_cents']);
    }

    public function test_multiple_payments_do_not_duplicate_aggregates_and_product_sorting_and_pagination_work(): void
    {
        config(['analytics.product_page_size' => 1]);
        $admin = User::factory()->admin()->create();
        [, $first] = $this->variant(price: 100, costCents: 2000, stock: 2);
        [, $second] = $this->variant(price: 100, costCents: 9000, stock: 2);
        $sale = $this->sale($admin, $first, 1, 10000);
        $sale->payments()->create(['payment_method' => 'card', 'amount_cents' => 0, 'status' => 'completed', 'created_by' => $admin->id]);
        $this->sale($admin, $second, 1, 10000);

        $this->assertSame(20000, $this->summary()['revenue_cents']);
        $products = app(ProfitAnalyticsService::class)->productReport($this->filters(['sort' => 'highest_profit']));
        $this->assertSame(2, $products->total());
        $this->assertSame($first->id, (int) $products->first()->product_variant_id);
        $this->assertSame(1, $products->perPage());
    }

    public function test_inventory_value_uses_integer_cents_and_reports_missing_cost_stock_separately(): void
    {
        $this->variant(price: 100, costCents: 6000, stock: 3);
        $this->variant(price: 50, costCents: null, stock: 2);
        $inventory = app(ProfitAnalyticsService::class)->inventorySummary();

        $this->assertSame(18000, $inventory['cost_value_cents']);
        $this->assertSame(40000, $inventory['retail_value_cents']);
        $this->assertSame(12000, $inventory['potential_profit_cents']);
        $this->assertSame(1, $inventory['missing_cost_variants']);
        $this->assertSame(2, $inventory['missing_cost_units']);
        $this->assertSame(10000, $inventory['missing_cost_retail_value_cents']);
    }

    public function test_analytics_page_and_export_are_admin_only_and_csv_obeys_filters(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->customer()->create();
        [$product, $variant] = $this->variant(price: 10, costCents: 500, stock: 2);
        $this->sale($admin, $variant, 1, 1000);

        $this->get(route('admin.analytics.profit'))->assertRedirect(route('admin.login'));
        $this->actingAs($customer)->get(route('admin.analytics.profit'))->assertForbidden();
        $this->get(route('admin.analytics.profit.export'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.analytics.profit'))->assertOk()->assertSeeText('Profit Analytics')->assertSeeText('Net Sales');
        $response = $this->get(route('admin.analytics.profit.export', ['range' => 'last_30_days', 'product_id' => $product->id]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString($product->name, $csv);
        $this->assertStringContainsString($variant->sku, $csv);
        $this->assertStringContainsString('1000', $csv);
    }

    private function summary(array $extra = []): array
    {
        return app(ProfitAnalyticsService::class)->summary($this->filters($extra));
    }

    private function filters(array $extra = []): array
    {
        return array_merge([
            'start' => CarbonImmutable::now()->subYear()->startOfDay(),
            'end' => CarbonImmutable::now()->addDay()->endOfDay(),
        ], $extra);
    }

    private function variant(float $price, ?int $costCents, int $stock, ?Category $category = null, ?string $brand = 'Test Brand'): array
    {
        $category ??= Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => ProductStatus::Active,
            'name' => 'Product '.Str::upper(Str::random(5)),
            'brand' => $brand,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => $price,
            'cost_price_cents' => $costCents,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);

        return [$product, $variant];
    }

    private function sale(User $admin, ProductVariant $variant, int $quantity, int $paymentCents, array $discount = [], string $method = 'cash'): Order
    {
        return app(PosCheckoutService::class)->checkout($admin, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['variant_id' => $variant->id, 'quantity' => $quantity]],
            'discount' => $discount,
            'payments' => [['method' => $method, 'amount_cents' => $paymentCents]],
        ]);
    }
}
