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
    <div class="pm-product-grid grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-3 2xl:grid-cols-4">
        @foreach ($products as $product)
            @php($variants = $product->variants)
            @php($availableStock = (int) $variants->sum('stock_quantity'))
            @php($minPrice = $variants->min('price'))
            @php($maxPrice = $variants->max('price'))
            @php($image = $product->images->first())
            @php($storage = $variants->flatMap(fn ($variant) => $variant->optionValues->filter(fn ($value) => $value->productOption?->slug === 'storage'))->unique('id'))
            @php($colors = $variants->flatMap(fn ($variant) => $variant->optionValues->filter(fn ($value) => $value->productOption?->slug === 'color'))->unique('id')->sortBy('sort_order')->values())
            @php($primaryColor = $colors->first())
            @php($plans = $product->installmentPlans->filter(fn ($plan) => $plan->is_active && (!$plan->product_variant_id || $variants->contains('id', $plan->product_variant_id))))
            @php($plan = $plans->sortBy('installment_amount')->first())
            @php($hasLimitedOffer = ($showOfferBadges ?? false) && $product->offer_ends_at?->isFuture() && $variants->contains(fn ($variant) => $variant->compare_at_price && $variant->compare_at_price > $variant->price))
            <article class="pm-product-card group">
                <a href="{{ route('products.show', $product) }}" class="block">
                    <div class="pm-product-media">
                        @if($availableStock <= 5)<span class="pm-stock-badge pm-stock-badge--low">{{ __('storefront.only_left', ['count' => $availableStock]) }}</span>@else<span class="pm-stock-badge">{{ __('storefront.in_stock') }}</span>@endif
                        @if($hasLimitedOffer)<span class="pm-offer-badge">Offer ends {{ $product->offer_ends_at->format('M j') }}</span>@endif
                        @if ($image)
                            <img src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $image->alt_text ?: $product->name }}" class="h-full w-full object-contain transition duration-300 group-hover:scale-105">
                        @else
                            <span class="pm-product-mock" style="--mock-phone-color: {{ $primaryColor?->hex_value ?: '#2563eb' }}" aria-hidden="true"></span>
                        @endif
                    </div>
                    <div class="pm-product-body">
                        @if($product->brand)<p class="pm-product-brand">{{ $product->brand }}</p>@endif
                        <h3 class="pm-product-title">{{ $product->name }}</h3>
                        <p class="mt-2 truncate text-xs text-slate-500">{{ $storage->first()?->display_name ?: 'Standard' }} · {{ data_get($product->specifications, 'Connectivity', '5G') }} · {{ data_get($product->specifications, 'SIM', 'Dual SIM') }}</p>
                        <div class="mt-4 flex items-end justify-between gap-2"><p class="pm-product-price">${{ number_format((float) $minPrice, 0) }}@if((float)$minPrice !== (float)$maxPrice)–${{ number_format((float)$maxPrice, 0) }}@endif</p>@if($plan)<p class="text-right text-[11px] font-bold leading-4 text-[var(--pm-secondary)]">From {{ number_format((float) $plan->installment_amount, 2) }} × {{ $plan->number_of_payments }}</p>@endif</div>
                        <span class="pm-button pm-button--accent pm-product-cta mt-4 w-full justify-center px-3 py-2.5 text-sm">View options</span>
                    </div>
                </a>
                <button type="button" data-compare-product data-product-slug="{{ $product->slug }}" data-product-name="{{ $product->name }}" data-compare-url="{{ route('products.compare') }}" data-compare-label="{{ __('storefront.compare_phone') }}" data-added-label="{{ __('storefront.added_to_compare') }}" data-select-label="{{ __('storefront.select_up_to_three') }}" data-action-label="{{ __('storefront.compare_specifications') }}" data-selected-one="{{ trans_choice('storefront.phones_selected', 1, ['count' => '__COUNT__']) }}" data-selected-many="{{ trans_choice('storefront.phones_selected', 2, ['count' => '__COUNT__']) }}" aria-pressed="false" class="pm-compare-button"><span class="material-symbols-outlined text-base">compare_arrows</span> {{ __('storefront.compare_phone') }}</button>
            </article>
        @endforeach
    </div>
@endif

<script>
    window.addEventListener('load', () => {
                if (window.__taqqsitCompareInitialized) return;
                window.__taqqsitCompareInitialized = true;
                const buttons = [...document.querySelectorAll('[data-compare-product]')];
                if (!buttons.length) return;
                const firstButton = buttons[0];
                const copy = {compare: firstButton.dataset.compareLabel, added: firstButton.dataset.addedLabel, select: firstButton.dataset.selectLabel, action: firstButton.dataset.actionLabel, selectedOne: firstButton.dataset.selectedOne, selectedMany: firstButton.dataset.selectedMany};
                const storageKey = 'taqqsit.compare-products';
                let selected;
                try { selected = JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch { selected = []; }
                selected = selected.filter(item => item?.slug && item?.name).slice(0, 3);
                const tray = document.createElement('div');
                tray.className = 'fixed inset-x-4 bottom-5 z-30 mx-auto hidden max-w-xl rounded-2xl bg-slate-950 p-3 text-white shadow-2xl sm:flex sm:items-center sm:gap-3';
                document.body.append(tray);
                const render = () => {
                    buttons.forEach(button => {
                        const isSelected = selected.some(item => item.slug === button.dataset.productSlug);
                        button.setAttribute('aria-pressed', String(isSelected));
                        button.innerHTML = `<span class="material-symbols-outlined text-base">${isSelected ? 'check' : 'compare_arrows'}</span> ${isSelected ? copy.added : copy.compare}`;
                        button.classList.toggle('border-[var(--pm-primary)]', isSelected);
                        button.classList.toggle('text-[var(--pm-primary)]', isSelected);
                    });
                    if (!selected.length) return tray.classList.add('hidden');
                    const query = new URLSearchParams();
                    selected.forEach(item => query.append('products[]', item.slug));
                    const selectionLabel = (selected.length === 1 ? copy.selectedOne : copy.selectedMany).replace('__COUNT__', selected.length);
                    tray.innerHTML = `<div class="min-w-0 flex-1"><strong>${selectionLabel}</strong><span class="ml-2 text-xs text-slate-300">${copy.select}</span></div><a href="${firstButton.dataset.compareUrl}?${query.toString()}" class="mt-2 inline-flex justify-center rounded-xl bg-white px-3 py-2 text-sm font-extrabold text-slate-950 sm:mt-0">${copy.action}</a>`;
                    tray.classList.remove('hidden');
                };
                buttons.forEach(button => button.addEventListener('click', () => {
                    const index = selected.findIndex(item => item.slug === button.dataset.productSlug);
                    if (index >= 0) selected.splice(index, 1);
                    else if (selected.length < 3) selected.push({slug: button.dataset.productSlug, name: button.dataset.productName});
                    localStorage.setItem(storageKey, JSON.stringify(selected));
                    render();
                }));
                render();
    });
</script>
