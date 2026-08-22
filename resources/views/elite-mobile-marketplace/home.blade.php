@extends('layouts.elite-mobile-marketplace')

@section('title', 'Catalog')

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <main class="pm-storefront-main">
            <section class="pm-luxury-hero" aria-labelledby="luxury-hero-title">
                <div class="pm-luxury-hero__copy">
                    <p class="pm-luxury-kicker">{{ __('storefront.luxury_kicker') }}</p>
                    <h1 id="luxury-hero-title" class="pm-luxury-title">{{ __('storefront.luxury_title') }}</h1>
                    <p class="pm-luxury-description">{{ __('storefront.luxury_description') }}</p>
                    <div class="mt-7 flex flex-wrap gap-3"><a href="{{ route('catalog.index') }}" class="pm-button pm-button--luxury">{{ __('storefront.explore_collection') }} <span class="material-symbols-outlined text-lg">arrow_forward</span></a><a href="{{ route('installments.landing') }}" class="pm-button pm-button--luxury-outline">{{ __('storefront.discover_installments') }}</a></div>
                </div>
                <div class="pm-luxury-hero__art" aria-hidden="true"><div class="pm-luxury-orbit"></div><div class="pm-luxury-phone"><span></span></div><div class="pm-luxury-seal">T</div></div>
            </section>

            <section class="pm-service-ribbon" aria-label="Store promises">
                <div><span class="material-symbols-outlined">verified</span><p><strong>Curated devices</strong><small>Selected for lasting quality</small></p></div>
                <div><span class="material-symbols-outlined">payments</span><p><strong>Considered payments</strong><small>Clear schedules, no surprises</small></p></div>
                <div><span class="material-symbols-outlined">support_agent</span><p><strong>Personal assistance</strong><small>Sales support when you need it</small></p></div>
            </section>

            <section class="pm-category-showcase" aria-labelledby="shop-by-collection">
                <div class="pm-editorial-heading">
                    <p>Explore the collection</p>
                    <h2 id="shop-by-collection">Find the device that fits your world.</h2>
                </div>
                <nav class="pm-category-showcase__grid" aria-label="Browse categories">
                    @foreach($menuCategories->take(4) as $category)
                        <a href="{{ route('categories.show', $category) }}" class="pm-category-tile">
                            <span class="pm-category-tile__number">0{{ $loop->iteration }}</span>
                            <span class="material-symbols-outlined pm-category-tile__icon">{{ $category->icon ?: 'devices' }}</span>
                            <span class="pm-category-tile__name">{{ $category->name }}</span>
                            <span class="pm-category-tile__action">Discover <span class="material-symbols-outlined">north_east</span></span>
                        </a>
                    @endforeach
                    <a href="{{ route('catalog.index') }}" class="pm-category-tile pm-category-tile--all">
                        <span class="pm-category-tile__number">The edit</span>
                        <span class="material-symbols-outlined pm-category-tile__icon">apps</span>
                        <span class="pm-category-tile__name">View all products</span>
                        <span class="pm-category-tile__action">Browse catalog <span class="material-symbols-outlined">arrow_forward</span></span>
                    </a>
                </nav>
            </section>
            @if($limitedTimeOffers->isNotEmpty())
                <section class="pm-collection-section pm-collection-section--offer" aria-labelledby="limited-time-offers">
                    <div class="pm-section-head">
                        <div>
                            <p>Private selection · While supplies last</p>
                            <h2 id="limited-time-offers">Limited-time offers</h2>
                        </div>
                        <span class="pm-section-index">01</span>
                    </div>
                    @include('elite-mobile-marketplace.partials.product-grid', ['products' => $limitedTimeOffers, 'showOfferBadges' => true])
                </section>
            @endif
            @if($trendingProducts->isNotEmpty())
                <section class="pm-collection-section" aria-labelledby="trending-products">
                    <div class="pm-section-head">
                        <div>
                            <p>Popular right now</p>
                            <h2 id="trending-products">Trending products</h2>
                        </div>
                        <a href="{{ route('catalog.index') }}" class="pm-editorial-link">View the collection <span class="material-symbols-outlined">arrow_forward</span></a>
                    </div>
                    @include('elite-mobile-marketplace.partials.product-grid', ['products' => $trendingProducts])
                </section>
            @endif
            <section class="pm-collection-section" aria-labelledby="recommended-products">
                <div class="pm-section-head">
                    <div>
                        <p>Selected by Taqqsit</p>
                        <h2 id="recommended-products">Recommended products</h2>
                    </div>
                    <a href="{{ route('catalog.index') }}" class="pm-editorial-link">Shop all <span class="material-symbols-outlined">arrow_forward</span></a>
                </div>
                @include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])
            </section>

            <section class="pm-concierge-banner">
                <div><p class="pm-luxury-kicker">The Taqqsit concierge</p><h2>Not sure which device belongs in your life?</h2><p>Speak directly with our team for product guidance, availability, and payment support.</p></div>
                <x-public.support-button label="Talk to our concierge" inline />
            </section>
    </main>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
