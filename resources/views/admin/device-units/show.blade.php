@extends('admin.layouts.app')
@section('title', 'Inspect Device')
@section('heading', $deviceUnit->variant->product->name)
@section('page_description', $deviceUnit->condition_type->label().' · '.$deviceUnit->condition_grade?->label())
@section('content')
<div class="admin-grid admin-grid-2">
<section class="admin-card"><h3 class="admin-card__title">Protected identity</h3><dl><dt class="admin-label">IMEI (authorized view)</dt><dd>{{ $deviceUnit->imei }}</dd><dt class="admin-label">Serial number</dt><dd>{{ $deviceUnit->serial_number ?: '—' }}</dd><dt class="admin-label">Status</dt><dd>{{ $deviceUnit->status->label() }}</dd></dl><div class="admin-actions"><a class="admin-button" href="{{ route('admin.device-units.edit', $deviceUnit) }}">Edit</a>@if($deviceUnit->status->value !== 'sold')<form method="POST" action="{{ route('admin.device-units.retire', $deviceUnit) }}">@csrf @method('PATCH')<button class="admin-button admin-button--danger">Retire</button></form>@endif</div></section>
<section class="admin-card"><h3 class="admin-card__title">Commercial record</h3><p>Sale price: <strong>${{ number_format($deviceUnit->selling_price, 2) }}</strong></p><p>Acquisition cost: <strong>{{ $deviceUnit->acquisition_cost_cents === null ? '—' : '$'.number_format($deviceUnit->acquisition_cost_cents / 100, 2) }}</strong></p><p>{{ $deviceUnit->warranty_label }}</p></section>
<section class="admin-card" style="grid-column:1/-1"><h3 class="admin-card__title">Audit trail</h3><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>When</th><th>Event</th><th>Actor</th></tr></thead><tbody>@foreach($deviceUnit->events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ str($event->event_type)->replace('_', ' ')->headline() }}</td><td>{{ $event->actor?->email ?? 'System' }}</td></tr>@endforeach</tbody></table></div></section>
</div>
@endsection
