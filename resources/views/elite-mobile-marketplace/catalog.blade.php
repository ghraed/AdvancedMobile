@extends('layouts.elite-mobile-marketplace')

@section('title', $currentCategory?->name ?? ($searchTerm !== '' ? 'Search' : 'Catalog'))

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <div class="pm-site-layout">
      <x-public.desktop-sidebar :categories="$menuCategories" />
      <main class="min-w-0">
        <p class="text-xs font-extrabold uppercase tracking-[.14em] text-[var(--pm-primary)]">{{ $currentCategory ? 'Category' : ($searchTerm !== '' ? 'Search results' : 'Catalog') }}</p>
        <h1 class="mt-2 text-4xl font-black tracking-[-.04em] text-slate-950">{{ $currentCategory?->name ?? ($searchTerm !== '' ? 'Results for “'.$searchTerm.'”' : 'All products') }}</h1>
        @if ($currentCategory?->description || $currentCategory?->image)
            <div class="mt-5 flex gap-4 rounded-[22px] border border-[var(--pm-border)] bg-white p-4 shadow-sm">
                @if ($currentCategory->image)<img src="{{ asset('storage/'.$currentCategory->image) }}" alt="" class="h-20 w-20 rounded-xl object-cover">@endif
                @if ($currentCategory->description)<p class="max-w-3xl self-center text-sm leading-6 text-slate-600">{{ $currentCategory->description }}</p>@endif
            </div>
        @endif
        @if ($childCategories->isNotEmpty())
            <nav aria-label="Subcategories" class="mt-6 flex flex-wrap gap-2">
                @foreach ($childCategories as $child)<a href="{{ route('categories.show', $child) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-400">{{ $child->name }}</a>@endforeach
            </nav>
        @endif
        <form method="GET" action="{{ $currentCategory ? route('categories.show', $currentCategory) : route('catalog.index') }}" class="mt-8 grid gap-3 rounded-[22px] border border-[var(--pm-border)] bg-white/90 p-5 shadow-[0_12px_35px_rgba(15,23,42,.06)] sm:grid-cols-2 lg:grid-cols-4">
            <input name="q" value="{{ request('q') }}" placeholder="Search products" class="pm-form-control text-sm">
            <select name="category" class="pm-form-control text-sm"><option value="">All categories</option>@foreach($filterOptions['categories'] as $option)<option value="{{ $option->slug }}" @selected(request('category') === $option->slug)>{{ $option->name }}</option>@endforeach</select>
            @if($filterOptions['brands']->isNotEmpty())<select name="brand" class="pm-form-control text-sm"><option value="">All brands</option>@foreach($filterOptions['brands'] as $brand)<option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>@endforeach</select>@endif
            <select name="storage" class="pm-form-control text-sm"><option value="">All storage</option>@foreach($filterOptions['storage'] as $value)<option value="{{ $value }}" @selected(request('storage') === $value)>{{ $value }}</option>@endforeach</select>
            <select name="color" class="pm-form-control text-sm"><option value="">All colors</option>@foreach($filterOptions['color'] as $value)<option value="{{ $value }}" @selected(request('color') === $value)>{{ $value }}</option>@endforeach</select>
            <div class="flex gap-2"><input name="price_min" value="{{ request('price_min') }}" inputmode="decimal" placeholder="Min price" class="pm-form-control min-w-0 text-sm"><input name="price_max" value="{{ request('price_max') }}" inputmode="decimal" placeholder="Max price" class="pm-form-control min-w-0 text-sm"></div>
            <select name="payments" class="pm-form-control text-sm"><option value="">Any payment count</option>@foreach($filterOptions['payments'] as $count)<option value="{{ $count }}" @selected((string)request('payments') === (string)$count)>{{ $count }} payments</option>@endforeach</select>
            <select name="sort" class="pm-form-control text-sm"><option value="newest" @selected($selectedSort === 'newest')>Newest</option><option value="price_asc" @selected($selectedSort === 'price_asc')>Price: low to high</option><option value="price_desc" @selected($selectedSort === 'price_desc')>Price: high to low</option><option value="installment_asc" @selected($selectedSort === 'installment_asc')>Lowest installment</option><option value="name_asc" @selected($selectedSort === 'name_asc')>Name: A to Z</option></select>
            <input type="hidden" name="availability" value="in_stock"><button class="pm-button pm-button--accent justify-center">Apply filters</button>
        </form>
        <div class="mt-8">@include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])</div>
        @if ($products instanceof \Illuminate\Pagination\AbstractPaginator)
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
      </main>
    </div>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
