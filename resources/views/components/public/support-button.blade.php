@props(['href' => null, 'label' => 'WhatsApp sales & support', 'inline' => false])
@php
    $salesSupportNumber = preg_replace('/\D+/', '', (string) config('services.whatsapp.sales_support_number'));
    $supportHref = $href ?: ($salesSupportNumber ? 'https://wa.me/'.$salesSupportNumber.'?text='.rawurlencode('Hi, I need help with a product or purchase.') : null);
@endphp
@if ($supportHref)
    <a href="{{ $supportHref }}" target="_blank" rel="noopener noreferrer" class="{{ $inline ? 'pm-concierge-button' : 'pm-support-float' }}" aria-label="{{ $label }}" @unless($inline) title="{{ $label }}" @endunless>
        <svg class="pm-whatsapp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12.04 2A9.95 9.95 0 0 0 3.5 17.08L2 22l5.04-1.48A9.98 9.98 0 1 0 12.04 2Zm0 17.94a8.25 8.25 0 0 1-4.2-1.15l-.3-.18-3 0.88.9-2.92-.2-.3a8.2 8.2 0 1 1 6.8 3.67Zm4.5-6.16c-.25-.13-1.48-.73-1.7-.81-.23-.08-.4-.13-.57.13-.17.25-.65.8-.8.97-.15.17-.3.19-.55.06-.25-.13-1.06-.39-2.02-1.25-.75-.67-1.25-1.5-1.4-1.75-.15-.25-.02-.38.11-.5.12-.11.25-.3.38-.44.13-.15.17-.25.25-.42.08-.17.04-.32-.02-.44-.06-.13-.57-1.38-.78-1.89-.2-.49-.41-.42-.57-.43h-.48c-.17 0-.44.06-.67.32-.23.25-.88.86-.88 2.1 0 1.23.9 2.43 1.02 2.6.13.17 1.76 2.69 4.27 3.77.6.26 1.06.41 1.42.52.6.19 1.15.16 1.58.1.48-.07 1.48-.6 1.69-1.18.21-.58.21-1.08.15-1.18-.06-.1-.23-.17-.48-.3Z"/></svg>
        <span @class(['sr-only' => ! $inline])>{{ $label }}</span>
    </a>
@endif
