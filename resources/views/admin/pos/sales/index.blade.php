@extends('admin.layouts.app')

@section('title', 'POS Sales')
@section('heading', 'POS Sales')
@section('page_description', 'Search completed and refunded counter sales, then open or reprint any receipt.')
@section('breadcrumbs')<li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.pos.index') }}">POS</a></li><li>Sales</li>@endsection
@section('actions')<a class="admin-button" href="{{ route('admin.pos.index') }}"><span class="material-symbols-outlined">point_of_sale</span> New sale</a>@endsection

@section('content')
    <div class="admin-grid">
        <section class="admin-card admin-card--tight">
            <form class="admin-filter-bar" method="GET">
                <label class="admin-field"><span class="admin-label">Receipt</span><input class="admin-input" name="reference" value="{{ $filters['reference'] ?? '' }}" placeholder="POS-…"></label>
                <label class="admin-field"><span class="admin-label">From</span><input class="admin-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label class="admin-field"><span class="admin-label">To</span><input class="admin-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <label class="admin-field"><span class="admin-label">Payment</span><select class="admin-select" name="payment_method"><option value="">All</option>@foreach(['cash'=>'Cash','card'=>'Card','mixed'=>'Mixed'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['payment_method'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label class="admin-field"><span class="admin-label">Cashier</span><select class="admin-select" name="cashier_id"><option value="">All</option>@foreach($cashiers as $cashier)<option value="{{ $cashier->id }}" @selected((string)($filters['cashier_id'] ?? '')===(string)$cashier->id)>{{ $cashier->name }}</option>@endforeach</select></label>
                <label class="admin-field"><span class="admin-label">Product / SKU</span><input class="admin-input" name="product" value="{{ $filters['product'] ?? '' }}"></label>
                <label class="admin-field"><span class="admin-label">Status</span><select class="admin-select" name="status"><option value="">All</option><option value="completed" @selected(($filters['status'] ?? '')==='completed')>Completed</option><option value="refunded" @selected(($filters['status'] ?? '')==='refunded')>Refunded</option></select></label>
                <div class="admin-actions"><button class="admin-button" type="submit">Filter</button><a class="admin-link-button" href="{{ route('admin.pos.sales.index') }}">Reset</a></div>
            </form>
        </section>

        <section class="admin-card">
            @if($sales->isEmpty())
                <x-admin.empty-state title="No POS sales found" description="Complete a sale or adjust the filters." />
            @else
                <x-admin.data-table>
                    <thead><tr><th>Receipt</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Cashier</th><th></th></tr></thead>
                    <tbody>
                    @foreach($sales as $sale)
                        @php($methods = $sale->payments->pluck('payment_method')->unique()->values())
                        <tr>
                            <td><strong>{{ $sale->reference }}</strong></td><td>{{ $sale->created_at->format('M j, Y H:i') }}</td><td>{{ $sale->item_count ?? 0 }}</td><td><strong>${{ number_format($sale->total_cents / 100, 2) }}</strong></td><td>{{ $methods->count() > 1 ? 'Mixed' : str($methods->first() ?? '—')->headline() }}</td>
                            <td><span class="admin-status-badge {{ $sale->status === 'refunded' ? 'admin-status-badge--warning' : 'admin-status-badge--success' }}">{{ str($sale->status)->headline() }}</span></td><td>{{ $sale->cashier_name ?: $sale->cashier?->name ?: 'Former user' }}</td>
                            <td><div class="admin-actions"><a class="admin-link" href="{{ route('admin.pos.sales.show', $sale) }}">View</a><a class="admin-link" href="{{ route('admin.pos.sales.receipt', $sale) }}">Receipt</a></div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-admin.data-table>
                <x-admin.pagination :paginator="$sales" />
            @endif
        </section>
    </div>
@endsection
