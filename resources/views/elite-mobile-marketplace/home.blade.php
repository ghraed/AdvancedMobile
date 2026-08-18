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
                    <div class="flex flex-wrap gap-2" style='margin-top: 0;'><a href="{{ route('catalog.index') }}" class="rounded-full border border-slate-950 bg-slate-950 px-4 py-2 text-sm font-bold text-white">All</a>@foreach($menuCategories as $category)<a href="{{ route('categories.show', $category) }}" class="rounded-full border border-[var(--pm-border)] bg-white px-4 py-2 text-sm font-bold text-slate-700">{{ $category->name }}</a>@endforeach</div>
                </div>
                @include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])
            </section>
        </main>
    </div>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
