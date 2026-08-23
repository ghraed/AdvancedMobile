@extends('admin.layouts.app')

@section('title', $sale->reference)
@section('heading', $sale->reference)
@section('page_description', 'POS sale details and immutable transaction snapshots.')
@section('breadcrumbs')<li><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li><a href="{{ route('admin.pos.index') }}">POS</a></li><li><a href="{{ route('admin.pos.sales.index') }}">Sales</a></li><li>{{ $sale->reference }}</li>@endsection
@section('actions')<a class="admin-link-button" target="_blank" href="{{ route('admin.pos.sales.receipt', $sale) }}"><span class="material-symbols-outlined">print</span> Print receipt</a>@endsection

@section('content')
    <div class="admin-grid admin-grid-2">
        <section class="admin-card" style="grid-column:1/-1">
            <div class="admin-card__header"><div><h3 class="admin-card__title">Items</h3><p class="admin-card__copy">Sold {{ $sale->created_at->format('M j, Y \a\t H:i') }} by {{ $sale->cashier_name ?: $sale->cashier?->name }}</p></div><span class="admin-status-badge {{ $sale->status === 'refunded' ? 'admin-status-badge--warning' : 'admin-status-badge--success' }}">{{ str($sale->status)->headline() }}</span></div>
            <x-admin.data-table><thead><tr><th>Product</th><th>SKU</th><th>Unit price</th><th>Qty</th><th>Total</th></tr></thead><tbody>@foreach($sale->items as $item)<tr><td><strong>{{ $item->product_name }}</strong><div class="admin-help">{{ collect($item->variant_options)->map(fn($value,$key)=>$key.': '.$value)->join(' · ') }}</div></td><td>{{ $item->sku }}@if($item->barcode)<div class="admin-help">{{ $item->barcode }}</div>@endif</td><td>${{ number_format($item->unit_price_cents/100,2) }}</td><td>{{ $item->quantity }}</td><td><strong>${{ number_format($item->total_cents/100,2) }}</strong></td></tr>@endforeach</tbody></x-admin.data-table>
        </section>
        <section class="admin-card"><h3 class="admin-card__title">Payment</h3><div class="admin-grid" style="margin-top:18px">@foreach($sale->payments as $payment)<div class="admin-row-card"><div class="admin-inline" style="justify-content:space-between"><strong>{{ str($payment->payment_method)->headline() }}</strong><strong>${{ number_format($payment->amount_cents/100,2) }}</strong></div>@if($payment->reference)<div class="admin-help">Reference: {{ $payment->reference }}</div>@endif @if($payment->payment_method==='cash' && $payment->change_due_cents>0)<div class="admin-help">Received ${{ number_format($payment->cash_received_cents/100,2) }} · Change ${{ number_format($payment->change_due_cents/100,2) }}</div>@endif</div>@endforeach</div></section>
        <section class="admin-card"><h3 class="admin-card__title">Totals</h3><div class="admin-grid" style="gap:12px;margin-top:18px"><div class="admin-inline" style="justify-content:space-between"><span>Subtotal</span><strong>${{ number_format($sale->subtotal_cents/100,2) }}</strong></div><div class="admin-inline" style="justify-content:space-between"><span>Discount @if($sale->discount_type)({{ $sale->discount_type === 'percentage' ? rtrim(rtrim($sale->discount_value,'0'),'.').'%' : 'fixed' }})@endif</span><strong>−${{ number_format($sale->discount_cents/100,2) }}</strong></div><div class="admin-inline" style="justify-content:space-between;font-size:22px;border-top:1px solid var(--admin-border);padding-top:14px"><strong>Total</strong><strong>${{ number_format($sale->total_cents/100,2) }}</strong></div></div></section>
        @if($sale->status === 'completed')
            <section class="admin-card" style="grid-column:1/-1"><h3 class="admin-card__title">Full refund</h3><p class="admin-card__copy">Refunding restores all sold stock exactly once and preserves this sale.</p><form method="POST" action="{{ route('admin.pos.sales.refund',$sale) }}" class="admin-form-grid" style="margin-top:18px" onsubmit="return confirm('Refund this entire sale and restore stock?')">@csrf<label class="admin-field"><span class="admin-label">Reason</span><textarea class="admin-textarea" name="reason" required minlength="3" maxlength="1000"></textarea></label><div><button class="admin-button admin-button--danger" type="submit">Refund full sale</button></div></form></section>
        @elseif($sale->refunds->isNotEmpty())
            @php($refund=$sale->refunds->first())<section class="admin-card" style="grid-column:1/-1"><h3 class="admin-card__title">Refund {{ $refund->reference }}</h3><p class="admin-card__copy">{{ $refund->created_at->format('M j, Y H:i') }} by {{ $refund->refunded_by_name }} · {{ $refund->reason }}</p></section>
        @endif
    </div>
@endsection
