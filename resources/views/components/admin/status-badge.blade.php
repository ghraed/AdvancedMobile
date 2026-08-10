@props([
    'status' => 'neutral',
    'label' => null,
])

@php
    $variants = [
        'success' => 'admin-status-badge admin-status-badge--success',
        'danger' => 'admin-status-badge admin-status-badge--danger',
        'warning' => 'admin-status-badge admin-status-badge--warning',
        'neutral' => 'admin-status-badge admin-status-badge--neutral',
    ];
@endphp

<span {{ $attributes->class($variants[$status] ?? $variants['neutral']) }}>
    {{ $label ?? $slot }}
</span>
