<header class="pm-header">
    <div class="pm-header__brand-group">
        <button type="button" data-category-open class="pm-icon-button pm-icon-button--menu" aria-label="Open categories"><span class="material-symbols-outlined">menu</span></button>
        <a href="{{ url('/') }}" class="pm-brand"><span class="pm-brand-mark">T</span><span>Taqqsit</span></a>
    </div>
    <form action="{{ route('search') }}" class="pm-header-search">
        <label for="header-search" class="sr-only">Search products and categories</label>
        <input id="header-search" name="q" value="{{ request('q') }}" placeholder="Search products, categories...">
        <span class="material-symbols-outlined">search</span>
    </form>
    <div class="pm-header__actions">
        <a href="{{ route('installments.landing') }}" class="pm-header__installments"><span class="material-symbols-outlined">payments</span><span>Installments</span><span lang="ar">التقسيط</span></a>
        <button type="button" data-search-open class="pm-icon-button lg:hidden" aria-label="Search"><span class="material-symbols-outlined">search</span></button>
        <x-public.account-link />
        <a href="{{ route('catalog.index') }}" class="pm-icon-button" aria-label="Shop"><span class="material-symbols-outlined">shopping_cart</span></a>
    </div>
</header>
