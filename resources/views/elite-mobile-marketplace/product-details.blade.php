@php
    use App\Models\ProductOption;
    use App\Enums\ProductType;

    $catalogProduct = $productModel;
    $activeVariants = $catalogProduct->variants->filter(fn ($variant) => $variant->is_active)->values();
    $storageValues = $activeVariants->flatMap(fn ($variant) => $variant->optionValues->filter(fn ($value) => $value->productOption?->slug === ProductOption::STORAGE_SLUG))->unique('id')->sortBy('sort_order')->values();
    $colorValues = $activeVariants->flatMap(fn ($variant) => $variant->optionValues->filter(fn ($value) => $value->productOption?->slug === ProductOption::COLOR_SLUG))->unique('id')->sortBy('sort_order')->values();
    $initial = ($selectedDeviceUnit ?? null)?->variant ?? $activeVariants->filter(fn ($variant) => $variant->is_available)->sortBy('price')->first() ?? $activeVariants->sortBy('price')->first();
    $imageUrl = fn ($image) => asset('storage/'.$image->image_path);
    $variantData = $activeVariants->map(fn ($variant) => [
        'id' => $variant->id,
        'optionValueIds' => $variant->optionValues->pluck('id')->sort()->values(),
        'inStock' => $variant->is_available,
    ])->values();
    $initialIds = $initial?->optionValues->pluck('id')->sort()->values() ?? collect();
    $fallbackImages = $catalogProduct->images->whereNull('product_option_value_id')->map(fn ($image) => ['url' => $imageUrl($image), 'alt' => $image->alt_text ?: $catalogProduct->name])->values();
    $productData = [
        'productId' => $catalogProduct->id,
        'variants' => $variantData,
        'storageValues' => $storageValues->map(fn ($value) => ['id' => $value->id, 'name' => $value->display_name ?: $value->name])->values(),
        'colorValues' => $colorValues->map(fn ($value) => ['id' => $value->id, 'name' => $value->display_name ?: $value->name, 'hex' => $value->hex_value, 'image' => $value->swatch_image ? $imageUrl((object) ['image_path' => $value->swatch_image]) : null])->values(),
        'initialIds' => $initialIds,
        'initialPayload' => $initialVariantPayload ?? null,
        'deviceUnitId' => ($selectedDeviceUnit ?? null)?->id,
        'fallbackImages' => $fallbackImages,
        'primaryColor' => $initial?->color_option?->hex_value ?: '#2563eb',
        // Keep these same-origin. An absolute URL built from APP_URL can point at
        // a different local host/port than the one serving the storefront.
        'resolveUrl' => route('products.resolve-variant', $catalogProduct, false),
        'previewUrl' => route('products.purchase-preview', $catalogProduct, false),
        'confirmUrl' => route('products.confirm-purchase', $catalogProduct, false),
        'applicationUrl' => route('installments.create', [], false),
        'compatibilityCheckUrl' => $catalogProduct->product_type === ProductType::Accessory ? route('products.check-compatibility', $catalogProduct, false) : null,
    ];
    $specifications = collect($catalogProduct->specifications ?? []);
    $whatsAppShareUrl = 'https://wa.me/?text='.rawurlencode('Check out '.$catalogProduct->name.': '.route('products.show', $catalogProduct));
    $salesSupportNumber = preg_replace('/\D+/', '', (string) config('services.whatsapp.sales_support_number'));
    $whatsAppSupportUrl = $salesSupportNumber ? 'https://wa.me/'.$salesSupportNumber.'?text='.rawurlencode('Hi, I need sales or support help with '.$catalogProduct->name.': '.route('products.show', $catalogProduct)) : null;
@endphp

@extends('layouts.elite-mobile-marketplace')

@section('title', $catalogProduct->name.' - Product Details')

@section('content')
    <x-public.public-header />
    @if(isset($menuCategories))<x-public.category-drawer :categories="$menuCategories" />@endif
    <x-public.search-overlay />

    <script id="product-detail-data" type="application/json">@json($productData)</script>
    <main class="pm-product-page" data-product-detail>
        <nav aria-label="Breadcrumb" class="pm-breadcrumb"><a href="{{ route('catalog.index') }}" data-safe-back><span class="material-symbols-outlined">arrow_back</span> Collection</a><span>/</span>@if($catalogProduct->category)<a href="{{ route('categories.show', $catalogProduct->category) }}">{{ $catalogProduct->category->name }}</a><span>/</span>@endif<span aria-current="page">{{ $catalogProduct->name }}</span></nav>
        <div class="pm-detail-layout">
            <section class="pm-detail-gallery" aria-label="{{ $catalogProduct->name }} gallery">
                <div class="pm-detail-gallery__edition"><span>TAQQSIT EDIT</span><small>{{ now()->format('Y') }}</small></div>
                <div class="pm-detail-gallery__stage"><img data-main-image src="{{ $fallbackImages->first()['url'] ?? '' }}" alt="{{ $fallbackImages->first()['alt'] ?? $catalogProduct->name }}" class="h-full w-full object-contain" @if($fallbackImages->isEmpty()) hidden @endif><span data-main-mock class="pm-product-detail-mock" style="--mock-phone-color: {{ $initial?->color_option?->hex_value ?: '#687f68' }}" @if($fallbackImages->isNotEmpty()) hidden @endif aria-hidden="true"></span></div>
                <div class="pm-detail-thumbnails" data-thumbnails aria-label="Product image gallery"></div>
                <div class="pm-detail-gallery__caption"><span>01</span><p>Designed to be seen.<br>Selected to be lived with.</p></div>
            </section>
            <section class="pm-purchase-panel">
                <div class="pm-purchase-panel__topline"><span>Private collection</span><a href="{{ $whatsAppShareUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Share {{ $catalogProduct->name }} on WhatsApp"><span class="material-symbols-outlined">share</span> Share on WhatsApp</a></div>
                @if($catalogProduct->brand)<p class="pm-product-brand">{{ $catalogProduct->brand }}</p>@endif
                <h1>{{ $catalogProduct->name }}</h1>
                @if($catalogProduct->short_description)<p class="pm-purchase-panel__intro">{{ $catalogProduct->short_description }}</p>@endif
                @if($selectedDeviceUnit ?? null)
                    <section class="mt-5 rounded-2xl border border-[var(--pm-border)] bg-[var(--pm-surface-soft)] p-4" data-device-condition>
                        <div class="flex flex-wrap items-center gap-2"><strong>{{ $selectedDeviceUnit->condition_type->label() }}</strong><span>·</span><strong>{{ $selectedDeviceUnit->condition_grade?->label() }}</strong><span>·</span><span>{{ $selectedDeviceUnit->masked_imei }}</span></div>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">Battery health</dt><dd class="font-bold">{{ $selectedDeviceUnit->battery_health_percent === null ? 'Not reported' : $selectedDeviceUnit->battery_health_percent.'%' }}</dd></div><div><dt class="text-slate-500">Warranty</dt><dd class="font-bold">{{ $selectedDeviceUnit->warranty_label }}</dd></div></dl>
                        @if($selectedDeviceUnit->customer_visible_condition_notes)<p class="mt-3 text-sm">{{ $selectedDeviceUnit->customer_visible_condition_notes }}</p>@endif
                        @if($selectedDeviceUnit->known_defects)<div class="mt-3"><strong class="text-sm text-red-800">Known defects</strong><ul class="mt-1 list-disc pl-5 text-sm">@foreach($selectedDeviceUnit->known_defects as $defect)<li>{{ $defect }}</li>@endforeach</ul></div>@endif
                    </section>
                @endif
                <div class="pm-purchase-panel__price"><div><span>From</span><p data-price>{{ $initial ? '$'.number_format(($selectedDeviceUnit ?? null)?->selling_price ?? (float) $initial->price, 2) : 'Unavailable' }}</p><p data-compare-price>@if($initial?->compare_at_price) Was ${{ number_format((float) $initial->compare_at_price, 2) }} @endif</p></div><div class="pm-availability"><span></span><p data-stock class="{{ $initial?->is_available ? 'text-emerald-700' : 'text-red-700' }}" aria-live="polite">{{ $initial ? ($initial->is_available ? ($initial->available_stock <= 5 ? 'Only '.$initial->available_stock.' left in stock.' : 'In stock.') : 'Out of stock.') : '' }}</p></div></div><p data-status class="mt-2 text-sm text-[var(--pm-danger)]" aria-live="polite"></p>

                @if(($availableDeviceUnits ?? collect())->count() > 1)
                    <section class="mt-5"><h2 class="font-extrabold">Choose the exact device</h2><div class="mt-2 grid gap-2">@foreach($availableDeviceUnits as $unit)<a href="{{ route('device-units.show', [$catalogProduct, $unit]) }}" class="rounded-xl border p-3 text-sm {{ ($selectedDeviceUnit ?? null)?->id === $unit->id ? 'border-[var(--pm-primary)] bg-blue-50' : 'border-[var(--pm-border)]' }}"><strong>{{ $unit->condition_grade?->label() }}</strong> · Battery {{ $unit->battery_health_percent ?? '—' }}% · ${{ number_format($unit->selling_price, 2) }}<span class="block text-xs">{{ $unit->masked_imei }}</span></a>@endforeach</div></section>
                @endif

                <section class="pm-option-studio">
                    @if($storageValues->isNotEmpty())<fieldset><div class="flex items-center justify-between gap-3"><legend class="font-extrabold text-[var(--pm-text)]">Storage</legend><span data-selected-storage class="text-sm font-bold text-[var(--pm-text-muted)]">{{ $initial?->storage_option?->display_name }}</span></div><div data-storage-options class="mt-3 grid grid-cols-3 gap-2">@foreach($storageValues as $value)<button type="button" disabled class="rounded-xl border border-[var(--pm-border)] bg-white px-2 py-3 text-sm font-bold text-[var(--pm-text)]">{{ $value->display_name ?: $value->name }}</button>@endforeach</div></fieldset>@endif
                    @if($colorValues->isNotEmpty())<fieldset class="{{ $storageValues->isNotEmpty() ? 'mt-6' : '' }}"><div class="flex items-center justify-between gap-3"><legend class="font-extrabold text-[var(--pm-text)]">Color</legend><span data-selected-color class="text-sm font-bold text-[var(--pm-text-muted)]">{{ $initial?->color_option?->display_name }}</span></div><div data-color-options class="mt-3 flex flex-wrap gap-3">@foreach($colorValues as $value)<button type="button" disabled title="{{ $value->display_name ?: $value->name }}" aria-label="{{ $value->display_name ?: $value->name }} color" class="h-9 w-9 rounded-full border-4 border-white shadow-[0_0_0_1px_#cbd5e1]" style="background: {{ $value->hex_value ?: '#cbd5e1' }}"><span class="sr-only">{{ $value->display_name ?: $value->name }}</span></button>@endforeach</div></fieldset>@endif
                </section>

                <section class="pm-option-studio" data-plan-section hidden>
                    <div class="flex items-center justify-between gap-3"><h2 class="font-extrabold text-[var(--pm-text)]">Installment plans</h2><span class="text-sm font-bold text-[var(--pm-primary)]">View schedule</span></div>
                    <div data-plan-options class="mt-3 grid grid-cols-3 gap-2"></div>
                    <div data-calendar class="mt-4 rounded-[17px] bg-slate-50 p-4 text-sm" aria-live="polite"></div>
                </section>
                <button data-purchase disabled class="pm-button pm-button--accent mt-6 w-full justify-center disabled:cursor-not-allowed disabled:opacity-50">Continue to purchase <span class="material-symbols-outlined">arrow_forward</span></button>
                <div class="pm-purchase-assurances"><span><i class="material-symbols-outlined">verified_user</i>Verified stock</span><span><i class="material-symbols-outlined">payments</i>Flexible payment</span><span><i class="material-symbols-outlined">support_agent</i>Personal support</span></div>
            </section>
        </div>

        @if($catalogProduct->description)
            <section class="pm-product-story">
                <div><p class="pm-luxury-kicker">The experience</p><h2>More than specifications.<br>A better everyday.</h2></div>
                <article><span class="pm-product-story__quote">“</span><p>{!! nl2br(e($catalogProduct->description)) !!}</p></article>
            </section>
        @endif
        @if($selectedDeviceUnit ?? null)
            <section class="pm-specification-suite" aria-labelledby="device-inspection"><div class="pm-editorial-heading"><p>Exact device inspection</p><h2 id="device-inspection">Condition, function, and contents.</h2></div>
                <dl>
                    @foreach($selectedDeviceUnit->condition_checklist ?? [] as $part => $state)<div><dt>{{ str($part)->replace('_', ' ')->headline() }}</dt><dd>{{ str($state)->replace('_', ' ')->headline() }}</dd><span class="material-symbols-outlined">{{ $state === 'fault' ? 'warning' : 'check' }}</span></div>@endforeach
                    @if($selectedDeviceUnit->included_accessories)<div><dt>Included accessories</dt><dd>{{ collect($selectedDeviceUnit->included_accessories)->map(fn($v) => str($v)->replace('_', ' ')->headline())->join(', ') }}</dd><span class="material-symbols-outlined">inventory_2</span></div>@endif
                    @if($selectedDeviceUnit->customer_visible_refurbishment_details)<div><dt>Refurbishment</dt><dd>{{ $selectedDeviceUnit->customer_visible_refurbishment_details }}</dd><span class="material-symbols-outlined">build</span></div>@endif
                    @if($selectedDeviceUnit->parts_replaced)<div><dt>Parts replaced</dt><dd>{{ implode(', ', $selectedDeviceUnit->parts_replaced) }}</dd><span class="material-symbols-outlined">published_with_changes</span></div>@endif
                </dl>
            </section>
        @endif
        @if($specifications->isNotEmpty())
            <section class="pm-specification-suite" aria-labelledby="product-specifications"><div class="pm-editorial-heading"><p>Inside the device</p><h2 id="product-specifications">Specifications, beautifully considered.</h2></div><dl>@foreach($specifications as $key => $specification) @php($label = is_array($specification) ? ($specification['key'] ?? 'Specification') : $key) @php($value = is_array($specification) ? ($specification['value'] ?? '') : $specification)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd><span class="material-symbols-outlined">check</span></div>@endforeach</dl></section>
        @endif
        @if($catalogProduct->product_type === ProductType::Device)
            <section class="pm-collection-section" aria-labelledby="compatible-accessories-title">
                <div class="pm-section-head"><div><p>Verified matches</p><h2 id="compatible-accessories-title">Compatible Accessories</h2></div><a class="pm-editorial-link" href="{{ route('accessories.compatible', ['device' => $catalogProduct->id]) }}">View all <span class="material-symbols-outlined">arrow_forward</span></a></div>
                @foreach(['case', 'screen_protector', 'charger', 'cable', 'power_bank', 'wireless_charger', 'headphones', 'adapter', 'other'] as $subtype)
                    @if(($compatibleAccessories[$subtype] ?? collect())->isNotEmpty())
                        <div class="mb-8" data-compatibility-group="{{ $subtype }}">
                            <h3 class="mb-3 text-lg font-black">{{ str($subtype)->headline()->plural() }}</h3>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($compatibleAccessories[$subtype] as $match)
                                    <a href="{{ route('products.show', $match['product']) }}" class="rounded-2xl border border-[var(--pm-border)] bg-white p-4 transition hover:border-[var(--pm-primary)]"><strong class="block">{{ $match['product']->name }}</strong><span class="mt-1 block text-sm text-[var(--pm-text-muted)]">{{ $match['reason'] }}</span></a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
                @if(($compatibleAccessories ?? collect())->isEmpty())<p class="text-[var(--pm-text-muted)]">No compatible in-stock accessories are published yet.</p>@endif
            </section>
        @endif
        @if($catalogProduct->product_type === ProductType::Accessory)
            <section class="pm-collection-section" aria-labelledby="compatible-devices-title">
                <div class="pm-section-head"><div><p>Device compatibility</p><h2 id="compatible-devices-title">Compatible With</h2></div></div>
                @if(($compatibleDevices ?? collect())->isNotEmpty())
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($compatibleDevices as $match)
                            <a href="{{ route('products.show', $match['product']) }}" class="rounded-2xl border border-[var(--pm-border)] bg-white p-4"><strong class="block">{{ $match['product']->name }}</strong><span class="mt-1 block text-sm text-[var(--pm-text-muted)]">{{ $match['reason'] }}</span></a>
                        @endforeach
                    </div>
                @else
                    <p class="text-[var(--pm-text-muted)]">No confirmed compatible devices are published yet.</p>
                @endif
                <div class="mt-8 max-w-2xl rounded-2xl border border-[var(--pm-border)] bg-white p-5" data-compatibility-checker>
                    <h3 class="text-lg font-black">Check compatibility with your phone</h3>
                    <label class="mt-4 block text-sm font-bold" for="compatibility-device">Choose a device</label>
                    <select id="compatibility-device" data-compatibility-device class="pm-form-control mt-1"><option value="">Select your phone</option>@foreach(($compatibilityCheckerDevices ?? collect()) as $device)<option value="{{ $device->id }}">{{ trim($device->brand.' '.$device->name) }}</option>@endforeach</select>
                    <button type="button" data-check-compatibility class="pm-button pm-button--accent mt-3">Check compatibility</button>
                    <p data-compatibility-result class="mt-3 font-bold" aria-live="polite"></p>
                    <p data-compatibility-reason class="mt-1 text-sm text-[var(--pm-text-muted)]"></p>
                </div>
            </section>
        @endif
        @if(($similarProducts ?? collect())->isNotEmpty())<section class="pm-collection-section" aria-labelledby="similar-products"><div class="pm-section-head"><div><p>Continue exploring · Similar products</p><h2 id="similar-products">You may also like</h2></div><a href="{{ route('catalog.index', ['category' => $catalogProduct->category?->slug]) }}" class="pm-editorial-link">Explore category <span class="material-symbols-outlined">arrow_forward</span></a></div>@include('elite-mobile-marketplace.partials.product-grid', ['products' => $similarProducts])</section>@endif
    </main>
    <x-public.support-button :href="$whatsAppSupportUrl" />
    <div data-purchase-modal hidden class="fixed inset-0 z-50 flex items-end bg-slate-950/50 p-0 sm:items-center sm:justify-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="purchase-preview-title">
        <section class="max-h-[90vh] w-full overflow-y-auto rounded-t-[28px] bg-white p-6 sm:max-w-lg sm:rounded-[28px]">
            <div class="flex items-start justify-between gap-4"><div><h2 id="purchase-preview-title" class="text-xl font-extrabold">Review payment calendar</h2><p data-modal-product class="mt-1 text-sm text-[var(--pm-text-muted)]"></p></div><button type="button" data-close-modal class="rounded-full p-1" aria-label="Close payment calendar"><span class="material-symbols-outlined">close</span></button></div>
            <div data-modal-summary class="mt-5 rounded-2xl bg-[var(--pm-surface-soft)] p-4 text-sm"></div>
            <div class="mt-5"><h3 class="font-bold">Payment schedule</h3><ol data-modal-schedule class="mt-3 space-y-2 text-sm"></ol></div>
            <p data-modal-status class="mt-4 text-sm" aria-live="polite"></p>
            <button type="button" data-confirm-purchase class="pm-button pm-button--accent mt-5 w-full justify-center">Confirm installment selection</button>
        </section>
    </div>
    @include('elite-mobile-marketplace.partials.bottom-nav')
@endsection

@push('scripts')
<script type="application/x-product-page-legacy">
(() => {
 const root=document.querySelector('[data-product-detail]'); if(!root)return; const config=document.getElementById('product-detail-data'); if(!config)return; let data; try{data=JSON.parse(config.textContent);}catch(error){root.querySelector('[data-status]').textContent='Unable to load product options. Please refresh the page.';return;} const $=s=>root.querySelector(s);
 const storage=$('[data-storage-options]'), colors=$('[data-color-options]'), main=$('[data-main-image]'), thumbs=$('[data-thumbnails]'), purchase=$('[data-purchase]'), modal=document.querySelector('[data-purchase-modal]'); let selected={storage:null,color:null}, payload=null, selectedPlan=null, preview=null;
 const button=(id,name,active,disabled,onClick,extra='')=>`<button type="button" ${disabled?'disabled':''} aria-pressed="${active}" class="rounded-2xl border px-3 py-2 text-sm font-semibold ${active?'border-[var(--pm-primary)] bg-[var(--pm-primary)] text-white':'border-[var(--pm-border)] bg-white text-[var(--pm-text)]'} ${disabled?'cursor-not-allowed opacity-40':''}" ${extra} data-option="${id}">${name}</button>`;
 function renderOptions(){
  if(storage) storage.innerHTML=data.storageValues.map(v=>button(v.id,v.name,selected.storage===v.id,!data.variants.some(x=>x.inStock&&x.optionValueIds.includes(v.id)),null)).join('');
  if(colors) colors.innerHTML=data.colorValues.map(v=>{const variants=selected.storage?data.variants.filter(x=>x.optionValueIds.includes(selected.storage)&&x.optionValueIds.includes(v.id)):data.variants.filter(x=>x.optionValueIds.includes(v.id)); const disabled=!variants.some(x=>x.inStock); const swatch=v.image?`<img src="${v.image}" alt="" class="h-5 w-5 rounded-full object-cover">`:v.hex?`<span aria-hidden="true" class="h-5 w-5 rounded-full border" style="background:${v.hex}"></span>`:''; return button(v.id,`${swatch}<span>${v.name}</span>`,selected.color===v.id,disabled,null,'aria-label="'+v.name+' color"');}).join('');
  storage?.querySelectorAll('[data-option]').forEach(b=>b.onclick=()=>choose('storage',Number(b.dataset.option))); colors?.querySelectorAll('[data-option]').forEach(b=>b.onclick=()=>choose('color',Number(b.dataset.option)));
 }
 function choose(type,id){preview=null;selected[type]=id; if(type==='storage'&&selected.color){const valid=data.variants.some(v=>v.inStock&&v.optionValueIds.includes(id)&&v.optionValueIds.includes(selected.color)); if(!valid) selected.color=data.colorValues.find(c=>data.variants.some(v=>v.inStock&&v.optionValueIds.includes(id)&&v.optionValueIds.includes(c.id)))?.id||null;} renderOptions(); resolve();}
 function images(items){const list=items?.length?items:data.fallbackImages; main.src=list[0].url; main.alt=list[0].alt; thumbs.innerHTML=list.map((img,i)=>`<button type="button" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border ${i===0?'border-[var(--pm-primary)]':'border-[var(--pm-border)]'}" aria-label="View image ${i+1}"><img src="${img.url}" alt="${img.alt}" class="h-full w-full object-contain"></button>`).join(''); thumbs.querySelectorAll('button').forEach((b,i)=>b.onclick=()=>{main.src=list[i].url;main.alt=list[i].alt;});}
 const money=value=>Number(value).toFixed(2);
 const paymentState=()=>{purchase.disabled=!(payload?.in_stock&&selectedPlan);};
 function plans(){const section=$('[data-plan-section]'), list=$('[data-plan-options]'), calendar=$('[data-calendar]'); if(!payload?.plans?.length){section.hidden=true;selectedPlan=null;paymentState();return;} section.hidden=false; selectedPlan=payload.plans.find(p=>p.id===selectedPlan?.id)||null; list.innerHTML=payload.plans.map(p=>`<button type="button" data-plan="${p.id}" aria-pressed="${p.id===selectedPlan?.id}" class="rounded-2xl border px-3 py-3 text-left ${p.id===selectedPlan?.id?'border-white bg-white text-[var(--pm-primary)]':'border-white/20 bg-white/10'}"><strong>${p.payments} total payments</strong><span class="mt-1 block text-xs">First payment today: ${money(p.amount_due_now)}</span><span class="block text-xs">Remaining: ${p.future_payment_count} ${p.interval} payments</span><span class="block text-xs">Each payment: ${money(p.installment_amount)}</span><span class="block text-xs font-bold">Total amount: ${money(p.total)}</span></button>`).join(''); list.querySelectorAll('[data-plan]').forEach(b=>b.onclick=()=>{selectedPlan=payload.plans.find(p=>p.id===Number(b.dataset.plan));plans();}); if(!selectedPlan){calendar.innerHTML='Select a plan to see its payment timeline.';paymentState();return;} const segments=[`<li class="flex-1 rounded-lg bg-white px-2 py-2 text-center text-[11px] text-[var(--pm-primary)]"><strong>Today</strong><br>Payment 1<br>${money(selectedPlan.amount_due_now)}</li>`,...selectedPlan.schedule.map(p=>`<li class="flex-1 rounded-lg bg-white/15 px-2 py-2 text-center text-[11px]"><strong>Payment ${p.sequence + 1}</strong><br>${p.due_date}<br>${money(p.amount)}</li>`)].join(''); calendar.innerHTML=`<strong>Payment timeline: ${selectedPlan.payments} total payments</strong><p class="mt-1 text-xs">The total is divided by ${selectedPlan.payments}; the first payment is due today.</p><ol class="mt-3 flex gap-1 overflow-x-auto">${segments}</ol>`;paymentState();}
 async function request(url, body){const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify(body)});const result=await response.json();if(!response.ok)throw new Error(result.message||'The selection changed. Please try again.');return result;}
 async function resolve(){ const ids=[selected.storage,selected.color].filter(Boolean); purchase.disabled=true; root.setAttribute('aria-busy','true'); if((data.storageValues.length&& !selected.storage)||(data.colorValues.length&&!selected.color)){ $('[data-status]').textContent='Choose all options to continue.'; root.setAttribute('aria-busy','false'); return; } try {const result=await request(data.resolveUrl,{option_value_ids:ids}); if(!result.resolved){payload=null;selectedPlan=null;$('[data-status]').textContent=result.message||'This option combination is unavailable.';$('[data-price]').textContent='Unavailable';$('[data-stock]').textContent='';images();plans();return;} payload=result;$('[data-price]').textContent=money(result.price);$('[data-compare-price]').textContent=result.compare_at_price?`Was ${money(result.compare_at_price)}`:'';$('[data-stock]').textContent=result.stock_message;$('[data-stock]').className=`mt-2 text-sm font-semibold ${result.in_stock?'text-emerald-700':'text-red-700'}`;$('[data-status]').textContent='';images(result.images);plans();}catch(error){payload=null;selectedPlan=null;$('[data-status]').textContent=error.message;plans();}finally{root.setAttribute('aria-busy','false');} }
 purchase.onclick=async()=>{if(!payload?.in_stock||!selectedPlan)return;purchase.disabled=true;try{preview=await request(data.previewUrl,{variant_id:payload.variant_id,plan_id:selectedPlan.id});$('[data-modal-product]').textContent=`${preview.product} · ${preview.storage||'No storage'} · ${preview.color||'No color'}`;$('[data-modal-summary]').innerHTML=`<div>Variant price: <strong>${money(preview.variant_price)}</strong></div><div>First payment today: <strong>${money(preview.amount_due_now)}</strong></div><div>Each payment: <strong>${money(preview.installment_amount)}</strong></div><div>Number of payments: <strong>${preview.installment_months}</strong></div><div>Total amount: <strong>${money(preview.total_amount)}</strong></div>`;$('[data-modal-schedule]').innerHTML=preview.future_installments.map(p=>`<li class="rounded-xl border border-[var(--pm-border)] p-3">Payment ${p.sequence + 1} · <strong>${p.due_date}</strong> · ${money(p.amount)}</li>`).join('');$('[data-modal-status]').textContent='';modal.hidden=false;}catch(error){$('[data-status]').textContent=error.message;}finally{paymentState();}};
 const closeModal=()=>{modal.hidden=true;purchase.focus();}; $('[data-close-modal]').onclick=closeModal; document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!modal.hidden)closeModal();}); $('[data-confirm-purchase]').onclick=async()=>{if(!preview)return;const confirm=$('[data-confirm-purchase]'),status=$('[data-modal-status]');confirm.disabled=true;confirm.textContent='Continuing to application…';try{const result=await request(data.confirmUrl,{variant_id:preview.variant_id,plan_id:preview.plan_id});status.className='mt-4 text-sm text-emerald-700';status.textContent=result.message;if(result.application_url)window.location.assign(result.application_url);}catch(error){status.className='mt-4 text-sm text-[var(--pm-danger)]';status.textContent=error.message;modal.hidden=false;resolve();}finally{confirm.disabled=false;confirm.textContent='Confirm installment selection';}};
 document.querySelector('[data-safe-back]')?.addEventListener('click',event=>{if(document.referrer&&new URL(document.referrer).origin===location.origin&&history.length>1){event.preventDefault();history.back();}});
 selected.storage=data.initialIds.find(id=>data.storageValues.some(v=>v.id===id))||null; selected.color=data.initialIds.find(id=>data.colorValues.some(v=>v.id===id))||null; renderOptions(); images(); if(data.initialPayload){const result=data.initialPayload;payload=result;$('[data-price]').textContent=money(result.price);$('[data-compare-price]').textContent=result.compare_at_price?`Was ${money(result.compare_at_price)}`:'';$('[data-stock]').textContent=result.stock_message;$('[data-stock]').className=`mt-2 text-sm font-semibold ${result.in_stock?'text-emerald-700':'text-red-700'}`;images(result.images);plans();} resolve();
})();
</script>
@endpush
