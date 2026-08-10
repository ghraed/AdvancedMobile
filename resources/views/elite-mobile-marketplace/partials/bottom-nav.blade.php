@php
    $tabs = [
        ['key' => 'home', 'label' => 'Home', 'icon' => 'home', 'route' => url('/')],
        ['key' => 'shop', 'label' => 'Shop', 'icon' => 'storefront', 'route' => route('catalog.index')],
        ['key' => 'installments', 'label' => 'Installments', 'icon' => 'payments', 'route' => route('installments.landing')],
        ['key' => 'profile', 'label' => 'Account', 'icon' => 'person', 'route' => auth()->check() && auth()->user()->canAccessAdmin() ? route('admin.dashboard') : route('customer.login')],
    ];
@endphp

<nav class="pm-bottom-nav">
    @foreach ($tabs as $tab)
        @php
            $isActive = ($activeTab ?? 'home') === $tab['key'];
        @endphp
        <a
            href="{{ $tab['route'] }}"
            class="pm-bottom-nav__item {{ $isActive ? 'pm-bottom-nav__item--active' : '' }}"
        >
            <span class="material-symbols-outlined" @if($isActive) style="font-variation-settings: 'FILL' 1;" @endif>{{ $tab['icon'] }}</span>
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>
