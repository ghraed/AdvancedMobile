@if ($products->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
        <span class="material-symbols-outlined text-4xl text-slate-400">inventory_2</span>
        <h3 class="mt-3 font-semibold text-slate-900">{{ request()->hasAny(['q', 'category', 'brand', 'storage', 'color', 'price_min', 'price_max', 'payments']) ? 'No products match these filters' : 'Nothing available yet' }}</h3>
        <p class="mt-1 text-sm text-slate-500">{{ request()->hasAny(['q', 'category', 'brand', 'storage', 'color', 'price_min', 'price_max', 'payments']) ? 'Try clearing a filter or searching for something else.' : 'Products will appear here when they are active and in stock.' }}</p>
        @if (request()->hasAny(['q', 'category', 'brand', 'storage', 'color', 'price_min', 'price_max', 'payments']))
            <a href="{{ route('catalog.index') }}" class="mt-4 inline-block text-sm font-semibold text-slate-900 underline">Clear filters</a>
        @endif
    </div>
@else
    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 2xl:grid-cols-4">
        @foreach ($products as $product)
            @php($variants = $product->variants)
            @php($minPrice = $variants->min('price'))
            @php($maxPrice = $variants->max('price'))
            @php($image = $product->images->first())
            @php($storage = $variants->flatMap(fn ($variant) => $variant->optionValues->filter(fn ($value) => $value->productOption?->slug === 'storage'))->unique('id'))
            @php($colors = $variants->flatMap(fn ($variant) => $variant->optionValues->filter(fn ($value) => $value->productOption?->slug === 'color'))->unique('id')->sortBy('sort_order')->values())
            @php($primaryColor = $colors->first())
            @php($plans = $product->installmentPlans->filter(fn ($plan) => $plan->is_active && (!$plan->product_variant_id || $variants->contains('id', $plan->product_variant_id))))
            @php($plan = $plans->sortBy('installment_amount')->first())
            <article class="group overflow-hidden rounded-[22px] border border-slate-200/90 bg-white p-3 transition duration-300 hover:-translate-y-1.5 hover:shadow-[0_22px_45px_rgba(15,23,42,.11)]">
                <a href="{{ route('products.show', $product) }}" class="block">
                    <div class="relative flex aspect-[1/.88] items-center justify-center overflow-hidden rounded-[17px] bg-gradient-to-br from-slate-50 to-slate-200 p-5">
                        <span class="absolute left-3 top-3 z-10 rounded-full bg-green-100 px-2 py-1 text-[11px] font-black text-green-700">In stock</span>
                        @if ($image)
                            <img src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->alt_text ?: $product->name }}" class="h-full w-full object-contain transition duration-300 group-hover:scale-105">
                        @else
                            <span class="pm-product-mock" style="--mock-phone-color: {{ $primaryColor?->hex_value ?: '#2563eb' }}" aria-hidden="true"></span>
                        @endif
                    </div>
                    <div class="px-1 pb-1 pt-4">
                        @if($product->brand)<p class="truncate text-[11px] font-extrabold uppercase tracking-[.14em] text-slate-400">{{ $product->brand }}</p>@endif
                        <h3 class="mt-1 truncate font-extrabold text-slate-900">{{ $product->name }}</h3>
                        <p class="mt-2 truncate text-xs text-slate-500">{{ $storage->first()?->display_name ?: 'Standard' }} · {{ data_get($product->specifications, 'Connectivity', '5G') }} · {{ data_get($product->specifications, 'SIM', 'Dual SIM') }}</p>
                        <div class="mt-3 flex items-end justify-between gap-2"><p class="text-lg font-black text-slate-900">${{ number_format((float) $minPrice, 0) }}@if((float)$minPrice !== (float)$maxPrice)–${{ number_format((float)$maxPrice, 0) }}@endif</p>@if($plan)<p class="text-right text-[11px] font-extrabold text-[var(--pm-primary)]">From {{ number_format((float) $plan->installment_amount, 2) }} × {{ $plan->number_of_payments }}</p>@endif</div>
                        <span class="pm-button pm-button--accent mt-4 w-full justify-center px-3 py-2.5 text-sm">View options</span>
                    </div>
                </a>
            </article>
        @endforeach
    </div>
@endif
