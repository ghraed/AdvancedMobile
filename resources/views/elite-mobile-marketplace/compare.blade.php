@php
    $specificationKeys = $comparisonProducts->flatMap(function ($product) {
        return collect($product->specifications ?? [])->map(function ($specification, $key) {
            return is_array($specification) ? ($specification['key'] ?? null) : $key;
        });
    })->filter()->unique()->values();
    $specificationValue = function ($product, $wantedKey) {
        foreach ($product->specifications ?? [] as $key => $specification) {
            $label = is_array($specification) ? ($specification['key'] ?? null) : $key;
            if ($label === $wantedKey) return is_array($specification) ? ($specification['value'] ?? '—') : ($specification ?: '—');
        }
        return '—';
    };
@endphp

@extends('layouts.elite-mobile-marketplace')

@section('title', __('storefront.compare_phones'))

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <main class="pm-compare-page">
        <header class="pm-compare-hero">
            <div><a href="{{ route('catalog.index') }}"><span class="material-symbols-outlined">arrow_back</span> {{ __('storefront.back_to_catalog') }}</a><p class="pm-luxury-kicker">{{ __('storefront.side_by_side') }}</p><h1>{{ __('storefront.compare_phone_specifications') }}</h1><p>See the details that matter, presented clearly so the right choice feels effortless.</p></div>
            <div class="pm-compare-hero__mark" aria-hidden="true"><span class="material-symbols-outlined">compare_arrows</span><small>UP TO<br>THREE</small></div>
        </header>

        @if($comparisonProducts->count() < 2)
            <div class="pm-compare-empty"><span class="material-symbols-outlined">compare_arrows</span><p>Curate your comparison</p><h2>{{ __('storefront.choose_two_phones') }}</h2><p>{{ __('storefront.choose_two_copy') }}</p><a href="{{ route('catalog.index') }}" class="pm-button pm-button--accent">{{ __('storefront.browse_phones') }} <span class="material-symbols-outlined">arrow_forward</span></a></div>
        @else
            <section class="pm-compare-products" aria-label="Selected products">@foreach($comparisonProducts as $product) @php($image = $product->images->first())<a href="{{ route('products.show', $product) }}"><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div>@if($image)<img src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->alt_text ?: $product->name }}">@else<span class="material-symbols-outlined">smartphone</span>@endif</div><p>{{ $product->brand ?: __('storefront.phone') }}</p><h2>{{ $product->name }}</h2><strong>${{ number_format((float) $product->variants->min('price'), 0) }}</strong></a>@endforeach</section>
            <div class="pm-comparison-table"><table><thead><tr><th>{{ __('storefront.specification') }}</th>@foreach($comparisonProducts as $product)<th><span>{{ $product->brand ?: __('storefront.phone') }}</span>{{ $product->name }}</th>@endforeach</tr></thead><tbody><tr><th>{{ __('storefront.category') }}</th>@foreach($comparisonProducts as $product)<td>{{ $product->category?->name ?: '—' }}</td>@endforeach</tr>@foreach($specificationKeys as $key)<tr><th>{{ $key }}</th>@foreach($comparisonProducts as $product)<td>{{ $specificationValue($product, $key) }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        @endif
    </main>
    @include('elite-mobile-marketplace.partials.bottom-nav')
    <x-public.support-button />
@endsection
