@extends('layouts.elite-mobile-marketplace')

@section('title', 'Accessories for '.$device->name)

@section('content')
    <x-public.public-header />
    <x-public.category-drawer :categories="$menuCategories" />
    <x-public.search-overlay />

    <main class="pm-product-page">
        <nav aria-label="Breadcrumb" class="pm-breadcrumb"><a href="{{ route('products.show', $device) }}"><span class="material-symbols-outlined">arrow_back</span> {{ $device->name }}</a><span>/</span><span aria-current="page">Compatible accessories</span></nav>
        <section class="pm-collection-section" aria-labelledby="compatible-accessories-title">
            <div class="pm-section-head"><div><p>Compatibility checked server-side</p><h1 id="compatible-accessories-title">Accessories for {{ $device->name }}</h1></div><span>{{ $products->total() }} compatible</span></div>
            <form method="GET" action="{{ route('accessories.compatible') }}" class="mb-8 grid gap-3 rounded-2xl border border-[var(--pm-border)] bg-white p-4 sm:grid-cols-3">
                <input type="hidden" name="device" value="{{ $device->id }}">
                <label class="text-sm font-bold">Accessory type<select class="pm-form-control mt-1" name="subtype"><option value="">All types</option>@foreach($subtypes as $subtype)<option value="{{ $subtype->value }}" @selected(request('subtype') === $subtype->value)>{{ str($subtype->value)->headline() }}</option>@endforeach</select></label>
                <label class="text-sm font-bold">Category<select class="pm-form-control mt-1" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>@endforeach</select></label>
                <button class="pm-button pm-button--accent self-end justify-center">Apply filters</button>
            </form>
            @include('elite-mobile-marketplace.partials.product-grid', ['products' => $products])
            @if($products->hasPages())<div class="pm-pagination mt-8">{{ $products->links() }}</div>@endif
        </section>
    </main>
    @include('elite-mobile-marketplace.partials.bottom-nav')
@endsection
