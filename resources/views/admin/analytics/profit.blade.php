@extends('admin.layouts.app')

@section('title', 'Profit Analytics')
@section('heading', 'Profit Analytics')
@section('page_description', 'Revenue, historical cost, margin, refunds, and inventory value from immutable sale snapshots.')
@section('breadcrumbs')
    <li><a href="{{ route('admin.dashboard') }}">Admin</a></li>
    <li><span>Profit Analytics</span></li>
@endsection

@php
    $money = fn ($cents) => '$'.number_format(((int) $cents) / 100, 2);
    $margin = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).'%';
    $variantLabel = function ($json) {
        $options = json_decode((string) ($json ?: '{}'), true);
        return is_array($options) && $options !== [] ? collect($options)->map(fn ($value, $key) => $key.': '.$value)->implode(', ') : 'Default';
    };
    $changeLabel = fn ($value) => $value === null ? 'No prior baseline' : (($value >= 0 ? '+' : '').number_format($value, 1).'% vs previous');
    $queryWithoutPage = request()->except('page');
@endphp

@section('content')
    <form class="admin-card analytics-filters" method="GET" action="{{ route('admin.analytics.profit') }}">
        <div class="analytics-filter-grid">
            <label class="admin-field"><span class="admin-label">Date range</span><select class="admin-select" name="range" data-range-select>
                @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'last_7_days' => 'Last 7 days', 'last_30_days' => 'Last 30 days', 'this_month' => 'This month', 'last_month' => 'Last month', 'this_year' => 'This year', 'custom' => 'Custom'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['range'] ?? 'last_30_days') === $value)>{{ $label }}</option>
                @endforeach
            </select></label>
            <label class="admin-field custom-date"><span class="admin-label">From</span><input class="admin-input" type="date" name="date_from" value="{{ request('date_from') }}"></label>
            <label class="admin-field custom-date"><span class="admin-label">To</span><input class="admin-input" type="date" name="date_to" value="{{ request('date_to') }}"></label>
            <label class="admin-field"><span class="admin-label">Product</span><select class="admin-select" name="product_id"><option value="">All products</option>@foreach ($filter_options['products'] as $option)<option value="{{ $option->id }}" @selected((string) request('product_id') === (string) $option->id)>{{ $option->name }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Variant</span><select class="admin-select" name="variant_id"><option value="">All variants</option>@foreach ($filter_options['variants'] as $option)<option value="{{ $option->id }}" @selected((string) request('variant_id') === (string) $option->id)>{{ $option->product_name }} — {{ $option->sku }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Category</span><select class="admin-select" name="category_id"><option value="">All categories</option>@foreach ($filter_options['categories'] as $option)<option value="{{ $option->id }}" @selected((string) request('category_id') === (string) $option->id)>{{ $option->name }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Brand</span><select class="admin-select" name="brand"><option value="">All brands</option>@foreach ($filter_options['brands'] as $option)<option value="{{ $option }}" @selected(request('brand') === $option)>{{ $option }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Channel</span><select class="admin-select" name="sales_channel"><option value="">All channels</option><option value="online" @selected(request('sales_channel') === 'online')>Online</option><option value="pos" @selected(request('sales_channel') === 'pos')>POS</option></select></label>
            <label class="admin-field"><span class="admin-label">Payment</span><select class="admin-select" name="payment_method"><option value="">All methods</option>@foreach ($filter_options['payment_methods'] as $option)<option value="{{ $option }}" @selected(request('payment_method') === $option)>{{ str($option)->headline() }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Cashier</span><select class="admin-select" name="cashier_id"><option value="">All cashiers</option>@foreach ($filter_options['cashiers'] as $option)<option value="{{ $option->id }}" @selected((string) request('cashier_id') === (string) $option->id)>{{ $option->name }}</option>@endforeach</select></label>
        </div>
        <div class="admin-actions analytics-filter-actions">
            <button class="admin-button" type="submit">Apply filters</button>
            <a class="admin-link-button" href="{{ route('admin.analytics.profit') }}">Reset</a>
            <a class="admin-button admin-button--secondary" href="{{ route('admin.analytics.profit.export', $queryWithoutPage) }}">Export CSV</a>
            <span class="analytics-period">{{ $period['display_start']->format('M j, Y') }} – {{ $period['display_end']->format('M j, Y') }}</span>
        </div>
    </form>

    @if ($summary['unknown_cost_sales_cents'] > 0)
        <div class="analytics-warning" role="alert"><span class="material-symbols-outlined">warning</span><div><strong>Profit is incomplete for {{ $summary['unknown_cost_units'] }} sold unit(s).</strong><br>{{ $money($summary['unknown_cost_sales_cents']) }} of net sales has no historical cost snapshot and is excluded from COGS, profit, and margin.</div></div>
    @endif

    <div class="analytics-kpis">
        @foreach ([
            ['Net Sales', $money($summary['net_sales_cents']), null],
            ['Revenue', $money($summary['revenue_cents']), $changeLabel($changes['revenue'])],
            ['Gross Profit', $money($summary['gross_profit_cents']), $changeLabel($changes['profit'])],
            ['Gross Margin', $margin($summary['gross_margin_percent']), $changeLabel($changes['margin'])],
            ['COGS', $money($summary['cogs_cents']), 'Known-cost sales only'],
            ['Discounts', $money($summary['discount_cents']), null],
            ['Refunds', $money($summary['refund_cents']), null],
            ['Orders', number_format($summary['orders_count']), $changeLabel($changes['orders'])],
            ['Units Sold', number_format($summary['units_sold']), $changeLabel($changes['units'])],
            ['Average Order Value', $money($summary['average_order_value_cents']), null],
        ] as [$label, $value, $meta])
            <div class="admin-card admin-kpi"><span class="admin-kpi__label">{{ $label }}</span><strong class="admin-kpi__value">{{ $value }}</strong>@if ($meta)<span class="admin-kpi__meta">{{ $meta }}</span>@endif</div>
        @endforeach
    </div>

    <div class="admin-card analytics-section">
        <div class="admin-card__header"><div><h3 class="admin-card__title">Profit trend</h3><p class="admin-card__copy">Net sales, gross profit, and COGS grouped by {{ $trend['granularity'] }}.</p></div></div>
        @if ($trend['rows']->isEmpty())
            <x-admin.empty-state title="No finalized sales" message="No paid or refunded sales match the selected period and filters." />
        @else
            <div class="analytics-chart-wrap"><canvas id="profit-trend-chart" height="310" aria-label="Profit trend chart"></canvas></div>
            <div class="chart-legend"><span><i style="--legend:#2563eb"></i>Net sales</span><span><i style="--legend:#059669"></i>Gross profit</span><span><i style="--legend:#f59e0b"></i>COGS</span></div>
        @endif
    </div>

    <div class="admin-card analytics-section">
        <div class="admin-card__header"><div><h3 class="admin-card__title">Product profitability</h3><p class="admin-card__copy">Line snapshots preserve product, SKU, price, discount, and cost at the time of sale.</p></div><form method="GET">@foreach (request()->except(['sort', 'page']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<select class="admin-select" name="sort" onchange="this.form.submit()">@foreach (['highest_profit' => 'Highest profit', 'highest_revenue' => 'Highest revenue', 'highest_margin' => 'Highest margin', 'lowest_margin' => 'Lowest margin', 'most_units' => 'Most units', 'lowest_profit' => 'Lowest profit'] as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'highest_profit') === $value)>{{ $label }}</option>@endforeach</select></form></div>
        <div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Product / Variant</th><th>SKU</th><th>Units</th><th>Gross</th><th>Discounts</th><th>Refunds</th><th>Net Sales</th><th>COGS</th><th>Profit</th><th>Margin</th></tr></thead><tbody>
            @forelse ($products as $row)<tr><td><strong>{{ $row->product_name }}</strong><small>{{ $variantLabel($row->variant_options) }}</small></td><td>{{ $row->sku }}</td><td>{{ number_format($row->units_sold) }}</td><td>{{ $money($row->gross_sales_cents) }}</td><td>{{ $money($row->discount_cents) }}</td><td>{{ $money($row->refund_cents) }}</td><td>{{ $money($row->net_sales_cents) }}</td><td>{{ $money($row->cogs_cents) }}@if ($row->unknown_cost_sales_cents > 0)<small class="text-warning">Cost missing</small>@endif</td><td class="{{ $row->gross_profit_cents < 0 ? 'text-danger' : 'text-success' }}">{{ $money($row->gross_profit_cents) }}</td><td>{{ $margin($row->gross_margin_percent) }}</td></tr>@empty<tr><td colspan="10">No matching product sales.</td></tr>@endforelse
        </tbody></table></div>
        <div class="analytics-pagination">{{ $products->links() }}</div>
    </div>

    <div class="analytics-two-column">
        <div class="admin-card analytics-section"><div class="admin-card__header"><div><h3 class="admin-card__title">Category analytics</h3><p class="admin-card__copy">Each sale starts in its direct snapshot category and rolls up once through every ancestor.</p></div></div><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Category</th><th>Units</th><th>Net Sales</th><th>COGS</th><th>Profit</th><th>Margin</th></tr></thead><tbody>@forelse ($categories as $row)<tr><td>{{ $row['category_name'] }}</td><td>{{ $row['units_sold'] }}</td><td>{{ $money($row['net_sales_cents']) }}</td><td>{{ $money($row['cogs_cents']) }}</td><td>{{ $money($row['gross_profit_cents']) }}</td><td>{{ $margin($row['gross_margin_percent']) }}</td></tr>@empty<tr><td colspan="6">No category data.</td></tr>@endforelse</tbody></table></div></div>
        <div class="admin-card analytics-section"><div class="admin-card__header"><div><h3 class="admin-card__title">Brand analytics</h3><p class="admin-card__copy">Brand names come from immutable order-line snapshots.</p></div></div><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Brand</th><th>Units</th><th>Sales</th><th>Profit</th><th>Margin</th></tr></thead><tbody>@forelse ($brands as $row)<tr><td>{{ $row->brand }}</td><td>{{ $row->units_sold }}</td><td>{{ $money($row->net_sales_cents) }}</td><td>{{ $money($row->gross_profit_cents) }}</td><td>{{ $margin($row->gross_margin_percent) }}</td></tr>@empty<tr><td colspan="5">No brand data.</td></tr>@endforelse</tbody></table></div></div>
    </div>

    <div class="analytics-two-column">
        <div class="admin-card analytics-section"><div class="admin-card__header"><div><h3 class="admin-card__title">Sales channels</h3><p class="admin-card__copy">Null legacy channels remain query-safe and appear as unknown.</p></div></div><div class="analytics-channel-grid">@forelse ($channels as $row)<div class="analytics-channel"><span>{{ str($row->sales_channel)->headline() }}</span><strong>{{ $money($row->net_sales_cents) }}</strong><small>{{ $money($row->gross_profit_cents) }} profit · {{ $row->units_sold }} units</small></div>@empty<p>No channel data.</p>@endforelse</div></div>
        <div class="admin-card analytics-section"><div class="admin-card__header"><div><h3 class="admin-card__title">Inventory value</h3><p class="admin-card__copy">Current stock valued at current variant cost and retail price.</p></div></div><div class="inventory-grid"><div><span>Inventory cost</span><strong>{{ $money($inventory['cost_value_cents']) }}</strong></div><div><span>Potential retail</span><strong>{{ $money($inventory['retail_value_cents']) }}</strong></div><div><span>Potential gross profit</span><strong>{{ $money($inventory['potential_profit_cents']) }}</strong></div><div class="inventory-alert"><span>Missing cost</span><strong>{{ $inventory['missing_cost_variants'] }} variants</strong><small>{{ $inventory['missing_cost_units'] }} units · {{ $money($inventory['missing_cost_retail_value_cents']) }} retail value</small></div></div></div>
    </div>

    <div class="analytics-two-column">
        <div class="admin-card analytics-section"><div class="admin-card__header"><div><h3 class="admin-card__title">Loss-Making Products</h3><p class="admin-card__copy">Finalized, non-refunded lines where net selling price is below snapshotted cost.</p></div></div><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Product</th><th>Cost</th><th>Net/unit</th><th>Loss/unit</th><th>Total loss</th></tr></thead><tbody>@forelse ($loss_making as $row)<tr><td><strong>{{ $row->product_name }}</strong><small>{{ $row->sku }} · {{ $variantLabel($row->variant_options) }}</small></td><td>{{ $money($row->unit_cost_cents) }}</td><td>{{ $money($row->net_unit_price_cents) }}</td><td class="text-danger">{{ $money($row->loss_per_unit_cents) }}</td><td class="text-danger">{{ $money($row->total_loss_cents) }}</td></tr>@empty<tr><td colspan="5">No loss-making product sales.</td></tr>@endforelse</tbody></table></div></div>
        <div class="admin-card analytics-section"><div class="admin-card__header"><div><h3 class="admin-card__title">Low-Margin Products</h3><p class="admin-card__copy">Known-cost sales below the configured {{ number_format($low_margin_threshold, 1) }}% warning threshold.</p></div></div><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Product</th><th>Net Sales</th><th>Profit</th><th>Margin</th></tr></thead><tbody>@forelse ($low_margin as $row)<tr><td><strong>{{ $row->product_name }}</strong><small>{{ $row->sku }}</small></td><td>{{ $money($row->net_sales_cents) }}</td><td>{{ $money($row->gross_profit_cents) }}</td><td class="text-warning">{{ $margin($row->gross_margin_percent) }}</td></tr>@empty<tr><td colspan="4">No low-margin product sales.</td></tr>@endforelse</tbody></table></div></div>
    </div>

    <div class="admin-card analytics-section"><div class="admin-card__header"><div><h3 class="admin-card__title">Variants Missing Cost</h3><p class="admin-card__copy">Add cost in product management before selling these variants. Existing sale snapshots will remain unchanged.</p></div><strong>{{ $inventory['missing_cost_variants'] }}</strong></div><div class="analytics-table-wrap"><table class="analytics-table"><thead><tr><th>Product</th><th>SKU</th><th>Current stock</th><th></th></tr></thead><tbody>@forelse ($missing_cost_variants as $row)<tr><td>{{ $row->product_name }}</td><td>{{ $row->sku }}</td><td>{{ $row->stock_quantity }}</td><td><a class="admin-link-button" href="{{ route('admin.products.edit', $row->product_id) }}">Set cost</a></td></tr>@empty<tr><td colspan="4">All variants have a cost.</td></tr>@endforelse</tbody></table></div></div>
@endsection

@push('styles')
<style>
    .analytics-filters { margin-bottom: 18px; } .analytics-filter-grid { display:grid; grid-template-columns:repeat(5,minmax(150px,1fr)); gap:14px; } .analytics-filter-actions { margin-top:16px; align-items:center; } .analytics-period { margin-left:auto; color:var(--admin-muted); font-weight:600; }
    .analytics-warning { display:flex; gap:12px; padding:16px 18px; margin-bottom:18px; border:1px solid #fbbf24; border-radius:16px; background:#fffbeb; color:#92400e; }
    .analytics-kpis { display:grid; grid-template-columns:repeat(5,minmax(150px,1fr)); gap:16px; margin-bottom:18px; } .analytics-kpis .admin-kpi__value { font-size:24px; }
    .analytics-section { margin-bottom:18px; } .analytics-chart-wrap { position:relative; width:100%; min-height:310px; } #profit-trend-chart { width:100%; height:310px; }
    .chart-legend { display:flex; justify-content:center; gap:20px; color:var(--admin-muted); font-size:13px; } .chart-legend span { display:flex; align-items:center; gap:7px; } .chart-legend i { width:10px; height:10px; border-radius:50%; background:var(--legend); }
    .analytics-table-wrap { overflow:auto; } .analytics-table { width:100%; border-collapse:collapse; font-size:13px; } .analytics-table th,.analytics-table td { padding:11px 10px; border-bottom:1px solid var(--admin-border); text-align:right; white-space:nowrap; } .analytics-table th:first-child,.analytics-table td:first-child { text-align:left; } .analytics-table th { color:var(--admin-muted); text-transform:uppercase; letter-spacing:.04em; font-size:11px; } .analytics-table small { display:block; margin-top:4px; color:var(--admin-muted); }
    .analytics-pagination { margin-top:16px; } .analytics-two-column { display:grid; grid-template-columns:1fr 1fr; gap:18px; } .analytics-channel-grid,.inventory-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; } .analytics-channel,.inventory-grid>div { display:grid; gap:5px; padding:16px; border-radius:14px; background:var(--admin-surface-muted); } .analytics-channel span,.inventory-grid span { color:var(--admin-muted); font-size:12px; text-transform:uppercase; letter-spacing:.04em; } .analytics-channel strong,.inventory-grid strong { font-size:21px; } .analytics-channel small,.inventory-grid small { color:var(--admin-muted); } .inventory-alert { border:1px solid #fbbf24; background:#fffbeb!important; }
    .text-danger { color:var(--admin-danger)!important; font-weight:700; } .text-success { color:var(--admin-success)!important; font-weight:700; } .text-warning { color:var(--admin-warning)!important; font-weight:700; }
    @media(max-width:1200px){.analytics-filter-grid,.analytics-kpis{grid-template-columns:repeat(3,1fr)}} @media(max-width:800px){.analytics-filter-grid,.analytics-kpis,.analytics-two-column{grid-template-columns:1fr 1fr}.analytics-period{width:100%;margin-left:0}} @media(max-width:560px){.analytics-filter-grid,.analytics-kpis,.analytics-two-column,.analytics-channel-grid,.inventory-grid{grid-template-columns:1fr}}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const select = document.querySelector('[data-range-select]');
    const toggleCustom = () => document.querySelectorAll('.custom-date').forEach((field) => field.style.display = select?.value === 'custom' ? '' : 'none');
    select?.addEventListener('change', toggleCustom); toggleCustom();
    const canvas = document.getElementById('profit-trend-chart'); if (!canvas) return;
    const rows = @json($trend['rows']); const ratio = window.devicePixelRatio || 1; const rect = canvas.getBoundingClientRect(); canvas.width = rect.width * ratio; canvas.height = 310 * ratio;
    const ctx = canvas.getContext('2d'); ctx.scale(ratio, ratio); const width = rect.width; const height = 310; const pad = {l:58,r:18,t:18,b:46};
    const series = [{key:'net_sales_cents',color:'#2563eb'},{key:'gross_profit_cents',color:'#059669'},{key:'cogs_cents',color:'#f59e0b'}]; const values = rows.flatMap(row => series.map(item => Number(row[item.key]))); const max = Math.max(1,...values); const min = Math.min(0,...values); const span = max-min || 1;
    ctx.font='12px Inter'; ctx.fillStyle='#64748b'; ctx.strokeStyle='#e2e8f0'; ctx.lineWidth=1;
    for(let i=0;i<=4;i++){const y=pad.t+(height-pad.t-pad.b)*(i/4);ctx.beginPath();ctx.moveTo(pad.l,y);ctx.lineTo(width-pad.r,y);ctx.stroke();const value=max-span*(i/4);ctx.fillText('$'+(value/100).toLocaleString(undefined,{maximumFractionDigits:0}),4,y+4)}
    const x = i => pad.l+(rows.length===1?(width-pad.l-pad.r)/2:(width-pad.l-pad.r)*(i/(rows.length-1))); const y = value => pad.t+(height-pad.t-pad.b)*((max-value)/span);
    series.forEach(item=>{ctx.strokeStyle=item.color;ctx.lineWidth=2.5;ctx.beginPath();rows.forEach((row,i)=>{const px=x(i),py=y(Number(row[item.key]));i?ctx.lineTo(px,py):ctx.moveTo(px,py)});ctx.stroke()});
    const labelIndexes=[0,Math.floor((rows.length-1)/2),rows.length-1].filter((v,i,a)=>a.indexOf(v)===i); labelIndexes.forEach(i=>{const label=String(rows[i].bucket);ctx.fillStyle='#64748b';ctx.textAlign=i===0?'left':(i===rows.length-1?'right':'center');ctx.fillText(label,x(i),height-16)});
})();
</script>
@endpush
