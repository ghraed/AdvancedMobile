<header class="pm-header">
    <button type="button" data-category-open class="pm-icon-button" aria-label="Open categories"><span class="material-symbols-outlined">menu</span></button>
    <a href="{{ url('/') }}" class="pm-brand"><span class="pm-brand-mark">T</span><span>Taqqsit</span></a>
    <form action="{{ route('search') }}" class="pm-header-search">
        <label for="header-search" class="sr-only">Search products and categories</label>
        <input id="header-search" name="q" value="{{ request('q') }}" placeholder="Search products, categories...">
        <span class="material-symbols-outlined">search</span>
    </form>
    <div class="ml-auto flex items-center gap-2">
        <a href="{{ route('installments.landing') }}" class="hidden text-sm font-semibold text-[var(--pm-primary)] lg:inline">Installments / التقسيط</a>
        <button type="button" data-search-open class="pm-icon-button lg:hidden" aria-label="Search"><span class="material-symbols-outlined">search</span></button>
        <x-public.account-link />
        <a href="{{ route('catalog.index') }}" class="pm-icon-button" aria-label="Shop"><span class="material-symbols-outlined">shopping_cart</span></a>
    </div>
</header>
