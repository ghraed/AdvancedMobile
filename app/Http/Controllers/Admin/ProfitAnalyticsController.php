<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitAnalyticsRequest;
use App\Services\ProfitAnalyticsService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitAnalyticsController extends Controller
{
    public function index(ProfitAnalyticsRequest $request, ProfitAnalyticsService $analytics): View
    {
        return view('admin.analytics.profit', $analytics->report($request->validated()));
    }

    public function export(ProfitAnalyticsRequest $request, ProfitAnalyticsService $analytics): StreamedResponse
    {
        $filters = $request->validated();
        $period = $analytics->resolvePeriod($filters);
        $filters['start'] = $period['start'];
        $filters['end'] = $period['end'];
        $filename = 'profit-report-'.$period['display_start']->format('Ymd').'-'.$period['display_end']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($analytics, $filters): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Product', 'Variant', 'SKU', 'Units Sold', 'Gross Sales Cents', 'Discounts Cents', 'Refunds Cents', 'Net Sales Cents', 'COGS Cents', 'Gross Profit Cents', 'Gross Margin %', 'Unknown Cost Sales Cents']);
            foreach ($analytics->productExportRows($filters) as $row) {
                $options = json_decode((string) ($row->variant_options ?? '{}'), true);
                fputcsv($output, [
                    $row->product_name,
                    is_array($options) ? collect($options)->map(fn ($value, $key) => $key.': '.$value)->implode(', ') : '',
                    $row->sku,
                    (int) $row->units_sold,
                    (int) $row->gross_sales_cents,
                    (int) $row->discount_cents,
                    (int) $row->refund_cents,
                    (int) $row->net_sales_cents,
                    (int) $row->cogs_cents,
                    (int) $row->gross_profit_cents,
                    $row->gross_margin_percent,
                    (int) $row->unknown_cost_sales_cents,
                ]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
