@extends('layouts.elite-mobile-marketplace')

@section('title', 'Catalog')

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <div class="pm-site-layout">
        <x-public.desktop-sidebar :categories="$menuCategories" />
        <main class="min-w-0">
            <section aria-labelledby="featured-products">
                <div class="pm-section-head">
                    <nav aria-label="Browse categories" class="-mx-1 flex w-full snap-x snap-mandatory gap-2 overflow-x-auto px-1 pb-2 hide-scrollbar"><a href="{{ route('catalog.index') }}" class="shrink-0 snap-start rounded-full border border-slate-950 bg-slate-950 px-4 py-2 text-sm font-bold text-white">All</a>@foreach($menuCategories as $category)<a href="{{ route('categories.show', $category) }}" class="shrink-0 snap-start rounded-full border border-[var(--pm-border)] bg-white px-4 py-2 text-sm font-bold text-slate-700">{{ $category->name }}</a>@endforeach</nav>
                </div>
                @include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])
            </section>
        </main>
    </div>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
