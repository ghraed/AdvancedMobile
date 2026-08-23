@extends('layouts.elite-mobile-marketplace')

@section('title', $pageTitle ?? $currentCategory?->name ?? ($searchTerm !== '' ? 'Search' : 'Catalog'))

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <main class="pm-catalog-page">
        <header class="pm-page-masthead">
            <div class="pm-page-masthead__content">
                <nav aria-label="Breadcrumb"><a href="{{ url('/') }}">Home</a><span>/</span><span>Collection</span></nav>
                <p class="pm-luxury-kicker">{{ $currentCategory ? 'Curated category' : ($searchTerm !== '' ? 'Your search' : 'The complete collection') }}</p>
                <h1>{{ $pageTitle ?? $currentCategory?->name ?? ($searchTerm !== '' ? 'Results for “'.$searchTerm.'”' : 'Objects of desire, made for every day.') }}</h1>
                <p class="pm-page-masthead__description">{{ $currentCategory?->description ?: ($searchTerm !== '' ? 'A considered edit of products matching your search.' : 'Explore refined technology selected for performance, design, and a more effortless everyday experience.') }}</p>
            </div>
            <div class="pm-page-masthead__monogram" aria-hidden="true"><span>{{ $currentCategory ? mb_substr($currentCategory->name, 0, 1) : 'T' }}</span><small>TAQQSIT<br>COLLECTION</small></div>
        </header>

        @if ($childCategories->isNotEmpty())
            <nav aria-label="Subcategories" class="pm-subcategory-rail">
                @foreach ($childCategories as $child)<a href="{{ route('categories.show', $child) }}"><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $child->name }}<span class="material-symbols-outlined">north_east</span></a>@endforeach
            </nav>
        @endif

        <div class="pm-catalog-workspace">
            <aside class="pm-filter-studio">
                <div class="pm-filter-studio__heading"><p>Refine your edit</p><span class="material-symbols-outlined">tune</span></div>
                <form method="GET" action="{{ $currentCategory ? route('categories.show', $currentCategory) : route('catalog.index') }}">
                    <label>Search<input name="q" value="{{ request('q') }}" placeholder="Model or brand" class="pm-form-control"></label>
                    <label>Category<select name="category" class="pm-form-control"><option value="">All categories</option>@foreach($filterOptions['categories'] as $option)<option value="{{ $option->slug }}" @selected(request('category') === $option->slug)>{{ $option->name }}</option>@endforeach</select></label>
                    @if($filterOptions['brands']->isNotEmpty())<label>Maison / brand<select name="brand" class="pm-form-control"><option value="">All brands</option>@foreach($filterOptions['brands'] as $brand)<option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>@endforeach</select></label>@endif
                    <label>Condition<select name="condition" class="pm-form-control"><option value="">All conditions</option>@foreach($filterOptions['conditions'] as $option)<option value="{{ $option->value }}" @selected(request('condition') === $option->value)>{{ $option->label() }}</option>@endforeach</select></label>
                    <div class="pm-filter-pair"><label>Grade<select name="grade" class="pm-form-control"><option value="">Any</option>@foreach($filterOptions['grades'] as $option)<option value="{{ $option->value }}" @selected(request('grade') === $option->value)>{{ $option->label() }}</option>@endforeach</select></label><label>Battery health<select name="battery_min" class="pm-form-control"><option value="">Any</option>@foreach([90,85,80] as $level)<option value="{{ $level }}" @selected((string)request('battery_min') === (string)$level)>{{ $level }}%+</option>@endforeach</select></label></div>
                    <label>Warranty<select name="warranty" class="pm-form-control"><option value="">Any</option><option value="yes" @selected(request('warranty') === 'yes')>With warranty</option></select></label>
                    <div class="pm-filter-pair"><label>Storage<select name="storage" class="pm-form-control"><option value="">All</option>@foreach($filterOptions['storage'] as $value)<option value="{{ $value }}" @selected(request('storage') === $value)>{{ $value }}</option>@endforeach</select></label><label>Color<select name="color" class="pm-form-control"><option value="">All</option>@foreach($filterOptions['color'] as $value)<option value="{{ $value }}" @selected(request('color') === $value)>{{ $value }}</option>@endforeach</select></label></div>
                    <label>Price range<div class="pm-filter-pair"><input name="price_min" value="{{ request('price_min') }}" inputmode="decimal" placeholder="Minimum" class="pm-form-control"><input name="price_max" value="{{ request('price_max') }}" inputmode="decimal" placeholder="Maximum" class="pm-form-control"></div></label>
                    <label>Payment plan<select name="payments" class="pm-form-control"><option value="">Any schedule</option>@foreach($filterOptions['payments'] as $count)<option value="{{ $count }}" @selected((string)request('payments') === (string)$count)>{{ $count }} payments</option>@endforeach</select></label>
                    <label>Order by<select name="sort" class="pm-form-control"><option value="newest" @selected($selectedSort === 'newest')>Newest arrivals</option><option value="price_asc" @selected($selectedSort === 'price_asc')>Price: low to high</option><option value="price_desc" @selected($selectedSort === 'price_desc')>Price: high to low</option><option value="installment_asc" @selected($selectedSort === 'installment_asc')>Lowest installment</option><option value="name_asc" @selected($selectedSort === 'name_asc')>Name: A to Z</option></select></label>
                    <input type="hidden" name="availability" value="in_stock"><button class="pm-button pm-button--accent w-full justify-center">Apply selection <span class="material-symbols-outlined">arrow_forward</span></button>
                    @if(request()->query())<a href="{{ $currentCategory ? route('categories.show', $currentCategory) : route('catalog.index') }}" class="pm-filter-reset">Clear all filters</a>@endif
                </form>
            </aside>
            <section class="pm-catalog-results" aria-labelledby="catalog-results-title">
                <div class="pm-catalog-results__head"><div><p>The selection</p><h2 id="catalog-results-title">{{ $products->total() }} {{ Str::plural('piece', $products->total()) }}</h2></div><span>Available now</span></div>
                @include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])
                @if ($products instanceof \Illuminate\Pagination\AbstractPaginator)<div class="pm-pagination">{{ $products->links() }}</div>@endif
            </section>
        </div>
    </main>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
