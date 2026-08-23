@extends('admin.layouts.app')
@section('title', 'Device Inventory')
@section('heading', 'Used & Refurbished Device Inventory')
@section('page_description', 'Every physical device is individually traceable. Search identifiers securely by their normalized hash.')
@section('content')
<div class="admin-grid">
    <div class="admin-card admin-card--tight">
        <form method="GET" class="admin-filter-bar">
            <label class="admin-field"><span class="admin-label">IMEI, serial, SKU or product</span><input class="admin-input" name="search" value="{{ request('search') }}" placeholder="Exact IMEI/serial or catalog text"></label>
            <label class="admin-field"><span class="admin-label">Condition</span><select class="admin-select" name="condition_type"><option value="">All</option>@foreach($conditions as $option)<option value="{{ $option->value }}" @selected(request('condition_type') === $option->value)>{{ $option->label() }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Grade</span><select class="admin-select" name="condition_grade"><option value="">All</option>@foreach($grades as $option)<option value="{{ $option->value }}" @selected(request('condition_grade') === $option->value)>{{ $option->label() }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Status</span><select class="admin-select" name="status"><option value="">All</option>@foreach($statuses as $option)<option value="{{ $option->value }}" @selected(request('status') === $option->value)>{{ $option->label() }}</option>@endforeach</select></label>
            <label class="admin-field"><span class="admin-label">Battery minimum</span><input class="admin-input" type="number" min="0" max="100" name="battery_min" value="{{ request('battery_min') }}"></label>
            <div class="admin-actions"><button class="admin-button">Filter</button><a class="admin-link" href="{{ route('admin.device-units.index') }}">Clear</a></div>
        </form>
    </div>
    <div class="admin-card">
        <div class="admin-card__header"><div><h3 class="admin-card__title">{{ $units->total() }} devices</h3><p class="admin-card__copy">Full IMEI values are decrypted only inside authorized admin screens.</p></div><a class="admin-button" href="{{ route('admin.device-units.create') }}"><span class="material-symbols-outlined">add</span> Intake device</a></div>
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Device</th><th>Identifier</th><th>Condition</th><th>Battery</th><th>Price</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($units as $unit)<tr>
            <td><strong>{{ $unit->variant->product->brand }} {{ $unit->variant->product->name }}</strong><br><span class="admin-help">{{ $unit->variant->sku }}</span></td>
            <td><span title="Authorized admin view">{{ $unit->masked_imei }}</span></td>
            <td>{{ $unit->condition_type->label() }}<br><span class="admin-help">{{ $unit->condition_grade?->label() }}</span></td>
            <td>{{ $unit->battery_health_percent === null ? '—' : $unit->battery_health_percent.'%' }}</td>
            <td>${{ number_format($unit->selling_price, 2) }}</td>
            <td><span class="admin-status-badge {{ $unit->status->value === 'available' ? 'admin-status-badge--success' : '' }}">{{ $unit->status->label() }}</span></td>
            <td><div class="admin-inline"><a class="admin-link" href="{{ route('admin.device-units.show', $unit) }}">Inspect</a><a class="admin-link" href="{{ route('admin.device-units.edit', $unit) }}">Edit</a></div></td>
        </tr>@empty<tr><td colspan="7"><p class="admin-help">No matching physical devices.</p></td></tr>@endforelse
        </tbody></table></div><div class="admin-pagination">{{ $units->links() }}</div>
    </div>
</div>
@endsection
