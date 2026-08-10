@extends('layouts.elite-mobile-marketplace')

@section('title', 'Catalog')

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <div class="pm-site-layout">
        <x-public.desktop-sidebar :categories="$menuCategories" />
        <main class="min-w-0">
            <section class="pm-hero">
                <div class="pm-hero-copy">
                    <span class="pm-eyebrow"><span class="material-symbols-outlined text-base">auto_awesome</span> Smarter shopping, simpler payments</span>
                    <h1 class="pm-hero-title">Upgrade today.<br>Pay your way.</h1>
                    <p class="mt-5 max-w-xl leading-7 text-blue-100">Discover premium technology with clear monthly plans, transparent pricing, and a checkout designed for mobile.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('catalog.index') }}" class="pm-button pm-button--secondary">Browse products</a>
                        <a href="{{ route('customer.register') }}" class="pm-button pm-button--outline">Create account</a>
                    </div>
                </div>
                <div class="pm-device-stage" aria-hidden="true"><div class="pm-device-stack"><span class="pm-device"></span><span class="pm-device"></span><span class="pm-device"></span></div></div>
            </section>

            <section aria-labelledby="featured-products">
                <div class="pm-section-head">
                    <div><h2 id="featured-products" class="pm-section-title">Featured devices</h2><p class="mt-1 text-[var(--pm-text-muted)]">Popular models with flexible installment plans.</p></div>
                    <div class="flex flex-wrap gap-2"><a href="{{ route('catalog.index') }}" class="rounded-full border border-slate-950 bg-slate-950 px-4 py-2 text-sm font-bold text-white">All</a>@foreach($menuCategories as $category)<a href="{{ route('categories.show', $category) }}" class="rounded-full border border-[var(--pm-border)] bg-white px-4 py-2 text-sm font-bold text-slate-700">{{ $category->name }}</a>@endforeach</div>
                </div>
                @include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])
            </section>
        </main>
    </div>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
