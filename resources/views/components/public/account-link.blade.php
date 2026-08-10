@php($signedIn = auth()->check())
<a href="{{ $signedIn && auth()->user()->canAccessAdmin() ? route('admin.dashboard') : ($signedIn ? route('catalog.index') : route('customer.login')) }}" class="pm-icon-button" aria-label="{{ $signedIn ? 'Account' : 'Sign in' }}">
    <span class="material-symbols-outlined text-[20px]">person</span>
</a>
