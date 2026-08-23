<?php

namespace App\Services;

use App\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitAnalyticsService
{
    /**
     * Financial inclusion policy:
     * - completed orders are included only when payment_status is paid;
     * - fully refunded orders are retained so their refund reverses net sales,
     *   units and COGS;
     * - pending, cancelled, failed and unpaid orders never enter the dataset.
     * POS currently permits one immutable full refund per order.
     */
    public function report(array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $comparison = $this->previousPeriod($period['start'], $period['end']);
        $filters['start'] = $period['start'];
        $filters['end'] = $period['end'];
        $previousFilters = $filters + [];
        $previousFilters['start'] = $comparison['start'];
        $previousFilters['end'] = $comparison['end'];

        $summary = $this->summary($filters);
        $previous = $this->summary($previousFilters);

        return [
            'filters' => $filters,
            'period' => $period,
            'comparison_period' => $comparison,
            'summary' => $summary,
            'previous_summary' => $previous,
            'changes' => $this->changes($summary, $previous),
            'trend' => $this->trend($filters),
            'products' => $this->productReport($filters),
            'categories' => $this->categoryReport($filters),
            'brands' => $this->brandReport($filters),
            'channels' => $this->channelReport($filters),
            'loss_making' => $this->lossMakingProducts($filters),
            'low_margin' => $this->lowMarginProducts($filters),
            'inventory' => $this->inventorySummary(),
            'missing_cost_variants' => DB::table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNull('product_variants.cost_price_cents')
                ->select('product_variants.id', 'product_variants.product_id', 'products.name as product_name', 'product_variants.sku', 'product_variants.stock_quantity')
                ->orderByDesc('product_variants.stock_quantity')
                ->limit(20)
                ->get(),
            'low_margin_threshold' => (float) config('analytics.low_margin_threshold', 10),
            'filter_options' => $this->filterOptions(),
        ];
    }

    public function resolvePeriod(array $filters): array
    {
        $timezone = (string) config('analytics.timezone', config('app.timezone', 'UTC'));
        $now = CarbonImmutable::now($timezone);
        $preset = $filters['range'] ?? config('analytics.default_range', 'last_30_days');

        [$start, $end] = match ($preset) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'yesterday' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'this_month' => [$now->startOfMonth(), $now->endOfDay()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => [
                CarbonImmutable::parse((string) $filters['date_from'], $timezone)->startOfDay(),
                CarbonImmutable::parse((string) $filters['date_to'], $timezone)->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };

        return [
            'preset' => $preset,
            'start' => $start->utc(),
            'end' => $end->utc(),
            'display_start' => $start,
            'display_end' => $end,
        ];
    }

    public function summary(array $filters): array
    {
        $row = $this->baseQuery($filters)->selectRaw(implode(', ', [
            'COALESCE(SUM(order_items.subtotal_cents), 0) AS revenue_cents',
            'COALESCE(SUM(order_items.discount_cents), 0) AS discount_cents',
            'COALESCE(SUM('.$this->refundExpression().'), 0) AS refund_cents',
            'COALESCE(SUM(order_items.total_cents - '.$this->refundExpression().'), 0) AS net_sales_cents',
            'COALESCE(SUM('.$this->cogsExpression().'), 0) AS cogs_cents',
            'COALESCE(SUM('.$this->profitExpression().'), 0) AS gross_profit_cents',
            'COALESCE(SUM('.$this->netUnitsExpression().'), 0) AS units_sold',
            'COUNT(DISTINCT orders.id) AS orders_count',
            'COALESCE(SUM(CASE WHEN order_items.unit_cost_cents IS NOT NULL THEN order_items.total_cents - '.$this->refundExpression().' ELSE 0 END), 0) AS known_cost_sales_cents',
            'COALESCE(SUM(CASE WHEN order_items.unit_cost_cents IS NULL THEN order_items.total_cents - '.$this->refundExpression().' ELSE 0 END), 0) AS unknown_cost_sales_cents',
            'COALESCE(SUM(CASE WHEN order_items.unit_cost_cents IS NULL THEN '.$this->netUnitsExpression().' ELSE 0 END), 0) AS unknown_cost_units',
        ]))->first();

        $summary = collect((array) $row)->map(fn ($value) => (int) $value)->all();
        $summary['gross_margin_percent'] = $this->margin(
            $summary['gross_profit_cents'],
            $summary['known_cost_sales_cents'],
        );
        $summary['average_order_value_cents'] = $summary['orders_count'] === 0
            ? 0
            : intdiv($summary['net_sales_cents'], $summary['orders_count']);

        return $summary;
    }

    public function productReport(array $filters, bool $paginate = true): LengthAwarePaginator|Collection
    {
        $query = $this->productAggregateQuery($filters);
        $sort = $filters['sort'] ?? 'highest_profit';
        match ($sort) {
            'highest_revenue' => $query->orderByDesc('net_sales_cents'),
            'highest_margin' => $query->orderByRaw($this->marginSortExpression().' DESC'),
            'lowest_margin' => $query->orderByRaw($this->marginSortExpression().' ASC'),
            'most_units' => $query->orderByDesc('units_sold'),
            'lowest_profit' => $query->orderBy('gross_profit_cents'),
            default => $query->orderByDesc('gross_profit_cents'),
        };
        $query->orderBy('order_items.product_name')->orderBy('order_items.sku');

        if (! $paginate) {
            return $query->get()->map(fn ($row) => $this->normalizeAggregateRow($row));
        }

        $paginator = $query->paginate((int) config('analytics.product_page_size', 20))->withQueryString();
        $paginator->setCollection($paginator->getCollection()->map(fn ($row) => $this->normalizeAggregateRow($row)));

        return $paginator;
    }

    public function productExportRows(array $filters): \Generator
    {
        $query = $this->productAggregateQuery($filters)->orderBy('order_items.product_name')->orderBy('order_items.sku');
        foreach ($query->cursor() as $row) {
            yield $this->normalizeAggregateRow($row);
        }
    }

    public function inventorySummary(): array
    {
        $priceCents = $this->priceCentsExpression('product_variants.price');
        $row = DB::table('product_variants')->selectRaw(implode(', ', [
            'COALESCE(SUM(CASE WHEN cost_price_cents IS NOT NULL THEN stock_quantity * cost_price_cents ELSE 0 END), 0) AS cost_value_cents',
            'COALESCE(SUM(stock_quantity * '.$priceCents.'), 0) AS retail_value_cents',
            'COALESCE(SUM(CASE WHEN cost_price_cents IS NOT NULL THEN '.$this->signedExpression('stock_quantity').' * ('.$this->signedExpression($priceCents).' - '.$this->signedExpression('cost_price_cents').') ELSE 0 END), 0) AS potential_profit_cents',
            'COALESCE(SUM(CASE WHEN cost_price_cents IS NULL THEN 1 ELSE 0 END), 0) AS missing_cost_variants',
            'COALESCE(SUM(CASE WHEN cost_price_cents IS NULL THEN stock_quantity ELSE 0 END), 0) AS missing_cost_units',
            'COALESCE(SUM(CASE WHEN cost_price_cents IS NULL THEN stock_quantity * '.$priceCents.' ELSE 0 END), 0) AS missing_cost_retail_value_cents',
        ]))->first();

        return collect((array) $row)->map(fn ($value) => (int) $value)->all();
    }

    private function productAggregateQuery(array $filters): Builder
    {
        return $this->baseQuery($filters)
            ->selectRaw(implode(', ', [
                'order_items.product_id', 'order_items.product_variant_id', 'order_items.product_name',
                "COALESCE(MAX(CAST(order_items.variant_options AS CHAR)), '{}') AS variant_options", 'order_items.sku',
                'SUM('.$this->netUnitsExpression().') AS units_sold',
                'SUM(order_items.subtotal_cents) AS gross_sales_cents',
                'SUM(order_items.discount_cents) AS discount_cents',
                'SUM('.$this->refundExpression().') AS refund_cents',
                'SUM(order_items.total_cents - '.$this->refundExpression().') AS net_sales_cents',
                'SUM('.$this->cogsExpression().') AS cogs_cents',
                'SUM('.$this->profitExpression().') AS gross_profit_cents',
                'SUM(CASE WHEN order_items.unit_cost_cents IS NULL THEN order_items.total_cents - '.$this->refundExpression().' ELSE 0 END) AS unknown_cost_sales_cents',
            ]))
            ->groupBy('order_items.product_id', 'order_items.product_variant_id', 'order_items.product_name', 'order_items.sku');
    }

    private function categoryReport(array $filters): Collection
    {
        $direct = $this->baseQuery($filters)
            ->selectRaw(implode(', ', [
                'order_items.category_id', 'order_items.category_name',
                'SUM('.$this->netUnitsExpression().') AS units_sold',
                'SUM(order_items.total_cents - '.$this->refundExpression().') AS net_sales_cents',
                'SUM('.$this->cogsExpression().') AS cogs_cents',
                'SUM('.$this->profitExpression().') AS gross_profit_cents',
                'SUM(CASE WHEN order_items.unit_cost_cents IS NOT NULL THEN order_items.total_cents - '.$this->refundExpression().' ELSE 0 END) AS known_cost_sales_cents',
            ]))
            ->groupBy('order_items.category_id', 'order_items.category_name')->get();

        $categories = Category::query()->get(['id', 'parent_id', 'name'])->keyBy('id');
        $totals = [];
        foreach ($direct as $row) {
            $categoryId = $row->category_id ? (int) $row->category_id : null;
            $visited = [];
            while ($categoryId !== null && ! isset($visited[$categoryId])) {
                $visited[$categoryId] = true;
                $category = $categories->get($categoryId);
                if (! $category) {
                    break;
                }
                $totals[$categoryId] ??= ['category_id' => $categoryId, 'category_name' => $category->name, 'units_sold' => 0, 'net_sales_cents' => 0, 'cogs_cents' => 0, 'gross_profit_cents' => 0, 'known_cost_sales_cents' => 0];
                foreach (['units_sold', 'net_sales_cents', 'cogs_cents', 'gross_profit_cents', 'known_cost_sales_cents'] as $metric) {
                    $totals[$categoryId][$metric] += (int) $row->{$metric};
                }
                $categoryId = $category->parent_id ? (int) $category->parent_id : null;
            }
        }

        return collect($totals)->map(function (array $row): array {
            $row['gross_margin_percent'] = $this->margin($row['gross_profit_cents'], $row['known_cost_sales_cents']);

            return $row;
        })->sortByDesc('gross_profit_cents')->values();
    }

    private function brandReport(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->selectRaw(implode(', ', [
                "COALESCE(order_items.brand, 'Unbranded') AS brand",
                'SUM('.$this->netUnitsExpression().') AS units_sold',
                'SUM(order_items.total_cents - '.$this->refundExpression().') AS net_sales_cents',
                'SUM('.$this->profitExpression().') AS gross_profit_cents',
                'SUM(CASE WHEN order_items.unit_cost_cents IS NOT NULL THEN order_items.total_cents - '.$this->refundExpression().' ELSE 0 END) AS known_cost_sales_cents',
            ]))->groupBy('order_items.brand')->orderByDesc('gross_profit_cents')->get()
            ->map(fn ($row) => $this->normalizeAggregateRow($row));
    }

    private function channelReport(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->selectRaw(implode(', ', [
                "COALESCE(orders.sales_channel, 'unknown') AS sales_channel",
                'SUM(order_items.total_cents - '.$this->refundExpression().') AS net_sales_cents',
                'SUM('.$this->profitExpression().') AS gross_profit_cents',
                'SUM('.$this->netUnitsExpression().') AS units_sold',
            ]))->groupBy('orders.sales_channel')->orderByDesc('net_sales_cents')->get();
    }

    private function lossMakingProducts(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->where('orders.status', 'completed')
            ->whereNotNull('order_items.unit_cost_cents')
            ->selectRaw(implode(', ', [
                'order_items.product_name', 'order_items.product_variant_id', "COALESCE(MAX(CAST(order_items.variant_options AS CHAR)), '{}') AS variant_options", 'order_items.sku',
                'SUM(order_items.quantity) AS units_sold',
                'SUM(order_items.total_cents) AS net_sales_cents',
                'SUM(order_items.unit_cost_cents * order_items.quantity) AS cogs_cents',
                $this->signedExpression('SUM(order_items.unit_cost_cents * order_items.quantity)').' - '.$this->signedExpression('SUM(order_items.total_cents)').' AS total_loss_cents',
            ]))->groupBy('order_items.product_name', 'order_items.product_variant_id', 'order_items.sku')
            ->havingRaw('SUM(order_items.total_cents) < SUM(order_items.unit_cost_cents * order_items.quantity)')
            ->orderByDesc('total_loss_cents')->limit(20)->get()
            ->map(function ($row): object {
                $row->net_unit_price_cents = (int) $row->units_sold === 0 ? 0 : intdiv((int) $row->net_sales_cents, (int) $row->units_sold);
                $row->unit_cost_cents = (int) $row->units_sold === 0 ? 0 : intdiv((int) $row->cogs_cents, (int) $row->units_sold);
                $row->loss_per_unit_cents = $row->unit_cost_cents - $row->net_unit_price_cents;

                return $row;
            });
    }

    private function lowMarginProducts(array $filters): Collection
    {
        $threshold = (float) config('analytics.low_margin_threshold', 10) / 100;

        return $this->productAggregateQuery($filters)
            ->havingRaw('SUM(CASE WHEN order_items.unit_cost_cents IS NOT NULL THEN order_items.total_cents - '.$this->refundExpression().' ELSE 0 END) > 0')
            ->havingRaw($this->marginSortExpression().' < ?', [$threshold])
            ->orderByRaw($this->marginSortExpression().' ASC')
            ->limit(20)->get()->map(fn ($row) => $this->normalizeAggregateRow($row));
    }

    private function trend(array $filters): array
    {
        $days = $filters['start']->diffInDays($filters['end']) + 1;
        [$expression, $granularity] = $this->trendExpression($days, ($filters['range'] ?? null) === 'today');
        $rows = $this->baseQuery($filters)
            ->selectRaw($expression.' AS bucket')
            ->selectRaw('SUM(order_items.total_cents - '.$this->refundExpression().') AS net_sales_cents')
            ->selectRaw('SUM('.$this->cogsExpression().') AS cogs_cents')
            ->selectRaw('SUM('.$this->profitExpression().') AS gross_profit_cents')
            ->groupBy('bucket')->orderBy('bucket')->get();

        return ['granularity' => $granularity, 'rows' => $rows];
    }

    private function trendExpression(int $days, bool $today): array
    {
        $driver = DB::connection()->getDriverName();
        $granularity = $today ? 'hour' : ($days <= 62 ? 'day' : ($days <= 370 ? 'week' : 'month'));
        if ($driver === 'sqlite') {
            return [match ($granularity) {
                'hour' => "strftime('%Y-%m-%d %H:00', orders.created_at)",
                'week' => "strftime('%Y-W%W', orders.created_at)",
                'month' => "strftime('%Y-%m', orders.created_at)",
                default => "strftime('%Y-%m-%d', orders.created_at)",
            }, $granularity];
        }

        return [match ($granularity) {
            'hour' => "DATE_FORMAT(orders.created_at, '%Y-%m-%d %H:00')",
            'week' => "DATE_FORMAT(orders.created_at, '%x-W%v')",
            'month' => "DATE_FORMAT(orders.created_at, '%Y-%m')",
            default => 'DATE(orders.created_at)',
        }, $granularity];
    }

    private function baseQuery(array $filters, bool $applyCategoryFilter = true): Builder
    {
        $query = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $paid) => $paid->where('orders.status', 'completed')->where('orders.payment_status', 'paid'))
                    ->orWhere(fn (Builder $refunded) => $refunded->where('orders.status', 'refunded')->where('orders.payment_status', 'refunded'));
            })
            ->whereBetween('orders.created_at', [$filters['start'], $filters['end']]);

        $query->when($filters['product_id'] ?? null, fn (Builder $q, $id) => $q->where('order_items.product_id', (int) $id));
        $query->when($filters['variant_id'] ?? null, fn (Builder $q, $id) => $q->where('order_items.product_variant_id', (int) $id));
        $query->when($filters['brand'] ?? null, fn (Builder $q, $brand) => $q->where('order_items.brand', $brand));
        $query->when($filters['sales_channel'] ?? null, fn (Builder $q, $channel) => $q->where('orders.sales_channel', $channel));
        $query->when($filters['cashier_id'] ?? null, fn (Builder $q, $id) => $q->where('orders.cashier_id', (int) $id));
        $query->when($filters['payment_method'] ?? null, fn (Builder $q, $method) => $q->whereExists(function (Builder $payment) use ($method): void {
            $payment->selectRaw('1')->from('order_payments')->whereColumn('order_payments.order_id', 'orders.id')->where('order_payments.payment_method', $method);
        }));

        if ($applyCategoryFilter && ($filters['category_id'] ?? null)) {
            $category = Category::query()->find((int) $filters['category_id']);
            $ids = $category ? [$category->id, ...$category->descendantIds()] : [(int) $filters['category_id']];
            $query->whereIn('order_items.category_id', $ids);
        }

        return $query;
    }

    private function previousPeriod(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $duration = $start->diffInSeconds($end) + 1;
        $previousEnd = $start->subSecond();

        return ['start' => $previousEnd->subSeconds($duration - 1), 'end' => $previousEnd];
    }

    private function changes(array $current, array $previous): array
    {
        return [
            'revenue' => $this->percentChange($current['revenue_cents'], $previous['revenue_cents']),
            'profit' => $this->percentChange($current['gross_profit_cents'], $previous['gross_profit_cents']),
            'margin' => $this->percentChange($current['gross_margin_percent'], $previous['gross_margin_percent']),
            'orders' => $this->percentChange($current['orders_count'], $previous['orders_count']),
            'units' => $this->percentChange($current['units_sold'], $previous['units_sold']),
        ];
    }

    private function percentChange(int|float|null $current, int|float|null $previous): ?float
    {
        return $current === null || $previous === null || (float) $previous === 0.0
            ? null
            : round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function margin(int $profitCents, int $knownNetSalesCents): ?float
    {
        return $knownNetSalesCents === 0 ? null : round(($profitCents / $knownNetSalesCents) * 100, 2);
    }

    private function normalizeAggregateRow(object $row): object
    {
        $knownSales = isset($row->known_cost_sales_cents)
            ? (int) $row->known_cost_sales_cents
            : (int) ($row->net_sales_cents ?? 0) - (int) ($row->unknown_cost_sales_cents ?? 0);
        $row->gross_margin_percent = $this->margin((int) ($row->gross_profit_cents ?? 0), $knownSales);

        return $row;
    }

    private function refundExpression(): string
    {
        return "CASE WHEN orders.status = 'refunded' THEN order_items.total_cents ELSE 0 END";
    }

    private function netUnitsExpression(): string
    {
        return "CASE WHEN orders.status = 'refunded' THEN 0 ELSE order_items.quantity END";
    }

    private function cogsExpression(): string
    {
        return "CASE WHEN orders.status <> 'refunded' AND order_items.unit_cost_cents IS NOT NULL THEN order_items.unit_cost_cents * order_items.quantity ELSE 0 END";
    }

    private function profitExpression(): string
    {
        return "CASE WHEN orders.status <> 'refunded' AND order_items.unit_cost_cents IS NOT NULL THEN ".$this->signedExpression('order_items.total_cents').' - '.$this->signedExpression('(order_items.unit_cost_cents * order_items.quantity)').' ELSE 0 END';
    }

    private function marginSortExpression(): string
    {
        return '(SUM('.$this->profitExpression().') / NULLIF(SUM(CASE WHEN order_items.unit_cost_cents IS NOT NULL THEN order_items.total_cents - '.$this->refundExpression().' ELSE 0 END), 0))';
    }

    private function priceCentsExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST(ROUND('.$column.' * 100) AS INTEGER)'
            : 'CAST(ROUND('.$column.' * 100) AS UNSIGNED)';
    }

    private function signedExpression(string $expression): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? 'CAST('.$expression.' AS INTEGER)'
            : 'CAST('.$expression.' AS SIGNED)';
    }

    private function filterOptions(): array
    {
        return [
            'products' => DB::table('products')->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            'variants' => DB::table('product_variants')->join('products', 'products.id', '=', 'product_variants.product_id')->orderBy('products.name')->orderBy('product_variants.sku')->get(['product_variants.id', 'product_variants.product_id', 'product_variants.sku', 'products.name as product_name']),
            'categories' => Category::query()->ordered()->get(['id', 'parent_id', 'name']),
            'brands' => DB::table('products')->whereNull('deleted_at')->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'cashiers' => DB::table('users')->join('orders', 'orders.cashier_id', '=', 'users.id')->distinct()->orderBy('users.name')->get(['users.id', 'users.name']),
            'payment_methods' => DB::table('order_payments')->distinct()->orderBy('payment_method')->pluck('payment_method'),
        ];
    }
}
