@props(['href' => null, 'label' => 'WhatsApp sales & support', 'inline' => false])
@php
    $salesSupportNumber = preg_replace('/\D+/', '', (string) config('services.whatsapp.sales_support_number'));
    $supportHref = $href ?: ($salesSupportNumber ? 'https://wa.me/'.$salesSupportNumber.'?text='.rawurlencode('Hi, I need help with a product or purchase.') : null);
@endphp
@if ($supportHref)
    <a href="{{ $supportHref }}" target="_blank" rel="noopener noreferrer" class="{{ $inline ? 'pm-concierge-button' : 'pm-support-float' }}"><span class="material-symbols-outlined">chat</span>{{ $label }}</a>
@endif
