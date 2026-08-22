<div class="pm-announcement">
    <p><span>Private technology edit</span><span>Flexible payment plans</span><span>Personal WhatsApp concierge</span></p>
    <a href="{{ route('installments.landing') }}">Discover Taqqsit <span class="material-symbols-outlined">arrow_forward</span></a>
</div>
<header class="pm-header">
    <div class="pm-header__brand-group">
        <button type="button" data-category-open data-catalog-url="{{ route('catalog.index') }}" class="pm-icon-button pm-icon-button--menu" aria-label="{{ __('storefront.open_categories') }}"><span class="material-symbols-outlined">menu</span></button>
        <a href="{{ url('/') }}" class="pm-brand"><span class="pm-brand-mark">T</span><span>Taqqsit</span></a>
    </div>
    <form action="{{ route('search') }}" class="pm-header-search">
        <label for="header-search" class="sr-only">{{ __('storefront.search') }}</label>
        <input id="header-search" name="q" value="{{ request('q') }}" placeholder="{{ __('storefront.search_placeholder') }}">
        <span class="material-symbols-outlined">search</span>
    </form>
    <div class="pm-header__actions">
        <a href="{{ route('installments.landing') }}" class="pm-header__installments"><span class="material-symbols-outlined">payments</span><span>{{ __('storefront.installments') }}</span></a>
        <button type="button" data-search-open class="pm-icon-button lg:hidden" aria-label="{{ __('storefront.search') }}"><span class="material-symbols-outlined">search</span></button>
        <form method="POST" action="{{ route('locale.update') }}" class="block">@csrf<label for="locale" class="sr-only">{{ __('storefront.language') }}</label><select id="locale" name="locale" onchange="this.form.submit()" class="pm-locale-select"><option value="en" @selected(app()->getLocale() === 'en')>EN</option><option value="fr" @selected(app()->getLocale() === 'fr')>FR</option><option value="ar" @selected(app()->getLocale() === 'ar')>العربية</option></select></form>
        <x-public.account-link />
        <a href="{{ route('catalog.index') }}" class="pm-icon-button" aria-label="{{ __('storefront.shop') }}"><span class="material-symbols-outlined">grid_view</span></a>
    </div>
</header>
<nav class="pm-collection-nav" aria-label="Primary navigation">
    <a href="{{ url('/') }}" @class(['is-active' => request()->is('/') || request()->is('home')])>The edit</a>
    <a href="{{ route('catalog.index') }}" @class(['is-active' => request()->routeIs('catalog.index') || request()->routeIs('categories.show')])>Shop all</a>
    <a href="{{ route('installments.landing') }}" @class(['is-active' => request()->routeIs('installments.*')])>Installments</a>
    <a href="{{ route('products.compare') }}" @class(['is-active' => request()->routeIs('products.compare')])>Compare</a>
    <span>Curated in Lebanon</span>
</nav>
