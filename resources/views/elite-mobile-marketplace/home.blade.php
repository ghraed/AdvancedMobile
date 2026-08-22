@extends('layouts.elite-mobile-marketplace')

@section('title', 'Catalog')

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <div class="pm-site-layout">
        <x-public.desktop-sidebar :categories="$menuCategories" />
        <main class="min-w-0">
            @if($limitedTimeOffers->isNotEmpty())
                <section class="mb-9" aria-labelledby="limited-time-offers">
                    <div class="pm-section-head items-center gap-4">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-[.16em] text-rose-600">While supplies last</p>
                            <h1 id="limited-time-offers" class="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Limited-time offers</h1>
                        </div>
                    </div>
                    <div class="mt-4">@include('elite-mobile-marketplace.partials.product-grid', ['products' => $limitedTimeOffers, 'showOfferBadges' => true])</div>
                </section>
            @endif
            @if($trendingProducts->isNotEmpty())
                <section class="mb-9" aria-labelledby="trending-products">
                    <div class="pm-section-head items-center gap-4">
                        <div>
                            <p class="text-xs font-extrabold uppercase tracking-[.16em] text-orange-600">Popular right now</p>
                            <h1 id="trending-products" class="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Trending products</h1>
                        </div>
                        <a href="{{ route('catalog.index') }}" class="ml-auto shrink-0 text-sm font-extrabold text-[var(--pm-primary)] hover:underline">View all</a>
                    </div>
                    <div class="mt-4">@include('elite-mobile-marketplace.partials.product-grid', ['products' => $trendingProducts])</div>
                </section>
            @endif
            <section aria-labelledby="recommended-products">
                <div class="pm-section-head items-center gap-4">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[.16em] text-[var(--pm-secondary)]">Picked for you</p>
                        <h1 id="recommended-products" class="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Recommended products</h1>
                    </div>
                    <a href="{{ route('catalog.index') }}" class="ml-auto shrink-0 text-sm font-extrabold text-[var(--pm-primary)] hover:underline">View all</a>
                    <nav aria-label="Browse categories" class="-mx-1 flex w-full snap-x snap-mandatory gap-2 overflow-x-auto px-1 pb-2 hide-scrollbar"><a href="{{ route('catalog.index') }}" class="shrink-0 snap-start rounded-full border border-slate-950 bg-slate-950 px-4 py-2 text-sm font-bold text-white">All</a>@foreach($menuCategories as $category)<a href="{{ route('categories.show', $category) }}" class="shrink-0 snap-start rounded-full border border-[var(--pm-border)] bg-white px-4 py-2 text-sm font-bold text-slate-700">{{ $category->name }}</a>@endforeach</nav>
                </div>
                @include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])
            </section>
        </main>
    </div>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
