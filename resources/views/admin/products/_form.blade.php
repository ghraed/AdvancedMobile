@php
    $productOptionsState = old('product_options', $product->productOptions->map(function ($option) {
        return [
            'id' => $option->id,
            'name' => $option->name,
            'slug' => $option->slug,
            'sort_order' => $option->sort_order,
            'is_active' => $option->is_active,
            'values' => $option->values->map(fn ($value) => [
                'id' => $value->id,
                'name' => $value->name,
                'display_name' => $value->display_name,
                'hex_value' => $value->hex_value,
                'swatch_image' => $value->swatch_image,
                'sort_order' => $value->sort_order,
                'is_active' => $value->is_active,
            ])->all(),
        ];
    })->all());

    if ($productOptionsState === []) {
        $productOptionsState = collect($optionDefinitions)->map(fn ($definition, $index) => [
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'sort_order' => $index,
            'is_active' => true,
            'values' => [],
        ])->all();
    }

    $productImagesState = old('product_images', $product->images->filter(fn ($image) => $image->product_option_value_id === null)->map(fn ($image) => [
        'id' => $image->id,
        'image_path' => $image->image_path,
        'alt_text' => $image->alt_text,
        'sort_order' => $image->sort_order,
        'is_primary' => $image->is_primary,
    ])->values()->all());

    $colorImagesState = old('color_images', $product->images
        ->filter(fn ($image) => $image->product_option_value_id !== null)
        ->groupBy('product_option_value_id')
        ->map(function ($images, $colorId) {
            $color = $images->first()->optionValue;
            return [
                'option_value_id' => (int) $colorId,
                'option_value_name' => $color?->name,
                'images' => $images->map(fn ($image) => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'alt_text' => $image->alt_text,
                    'sort_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                ])->values()->all(),
            ];
        })->values()->all());

    $variantsState = old('variants', $product->variants->map(function ($variant) {
        $optionValues = $variant->optionValues
            ->sortBy(fn ($value) => $value->productOption?->sort_order ?? 0)
            ->map(fn ($value) => [
                'id' => $value->id,
                'option_slug' => $value->productOption?->slug,
                'name' => $value->name,
            ])
            ->values()
            ->all();

        return [
            'id' => $variant->id,
            'client_key' => (string) $variant->id,
            'sku' => $variant->sku,
            'barcode' => $variant->barcode,
            'sku_auto' => true,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'cost_price' => $variant->cost_price_cents === null ? null : number_format($variant->cost_price_cents / 100, 2, '.', ''),
            'stock_quantity' => $variant->stock_quantity,
            'is_active' => $variant->is_active,
            'option_values' => $optionValues,
            'images' => $variant->images->map(fn ($image) => [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'alt_text' => $image->alt_text,
                'sort_order' => $image->sort_order,
                'is_primary' => $image->is_primary,
            ])->all(),
        ];
    })->all());

    $specificationsState = old('specifications', $product->specifications ?? []);
    if ($specificationsState === []) {
        $specificationsState = [['key' => '', 'value' => '']];
    }

    $installmentPlansState = old('installment_plans', $product->installmentPlans->map(fn ($plan) => [
        'id' => $plan->id,
        'scope' => $plan->product_variant_id ? 'variant' : 'product',
        'variant_key' => $plan->variant_key ?? (string) $plan->product_variant_id,
        'product_variant_id' => $plan->product_variant_id,
        'months' => $plan->months,
        'total_amount' => $plan->total_amount,
        'is_active' => $plan->is_active,
    ])->values()->all());

@endphp

<div class="admin-card">
    <div class="admin-card__header">
        <div>
            <h3 class="admin-card__title">Basic Information</h3>
            <p class="admin-card__copy">Set the product name, brand, publication status, and short summary.</p>
        </div>
        @if ($product->exists)
            <div class="admin-actions">
                <a href="{{ route('admin.products.preview', $product) }}" target="_blank" rel="noreferrer" class="admin-link-button">Preview</a>
            </div>
        @endif
    </div>

    <div class="admin-grid admin-grid-2">
        <div class="admin-field">
            <label class="admin-label" for="name">Product Name</label>
            <input class="admin-input" id="name" name="name" value="{{ old('name', $product->name) }}" required>
        </div>
        <div class="admin-field">
            <label class="admin-label" for="slug">Slug</label>
            <input class="admin-input" id="slug" name="slug" value="{{ old('slug', $product->slug) }}">
        </div>
        <div class="admin-field">
            <label class="admin-label" for="brand">Brand</label>
            <input class="admin-input" id="brand" name="brand" value="{{ old('brand', $product->brand) }}">
        </div>
        <div class="admin-field">
            <label class="admin-label" for="status">Product Status</label>
            <select class="admin-select" id="status" name="status" required>
                @foreach (\App\Enums\ProductStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(old('status', $product->status?->value ?? \App\Enums\ProductStatus::Draft->value) === $status->value)>
                        {{ str($status->value)->headline() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="admin-field">
            <label class="admin-label" for="published_at">Publication Date</label>
            <input
                class="admin-input"
                id="published_at"
                type="datetime-local"
                name="published_at"
                value="{{ old('published_at', optional($product->published_at)->format('Y-m-d\TH:i')) }}"
            >
        </div>
        <div class="admin-field">
            <label class="admin-label" for="offer_ends_at">Limited-time Offer Ends</label>
            <input
                class="admin-input"
                id="offer_ends_at"
                type="datetime-local"
                name="offer_ends_at"
                value="{{ old('offer_ends_at', optional($product->offer_ends_at)->format('Y-m-d\TH:i')) }}"
            >
            <p class="admin-help">Set this only for discounted variants. The offer is hidden automatically after this time.</p>
        </div>
        <div class="admin-field" style="grid-column: 1 / -1;">
            <label class="admin-label" for="short_description">Short Description</label>
            <input class="admin-input" id="short_description" name="short_description" value="{{ old('short_description', $product->short_description) }}">
        </div>
    </div>

    <div class="admin-inline" style="margin-top:16px;">
        <label style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="is_featured" value="0">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured ?? false))>
            <span>Featured product</span>
        </label>
        <label style="display:flex; gap:10px; align-items:center; margin-top:10px;">
            <input type="hidden" name="is_trending" value="0">
            <input type="checkbox" name="is_trending" value="1" @checked(old('is_trending', $product->is_trending ?? false))>
            <span>Show in trending products</span>
        </label>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card__header"><div><h3 class="admin-card__title">Category</h3><p class="admin-card__copy">Choose the catalog category for this product.</p></div></div>
    <div class="admin-field">
        <label class="admin-label" for="category_id">Category</label>
        <select class="admin-select" id="category_id" name="category_id" required>
            <option value="">Select category</option>
            @foreach ($categoryOptions as $categoryOption)
                <option value="{{ $categoryOption['id'] }}" @selected((string) old('category_id', $product->category_id) === (string) $categoryOption['id'])>{{ $categoryOption['label'] }}{{ $categoryOption['is_leaf'] ? '' : ' (Parent)' }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="admin-grid admin-grid-4">
    <div class="admin-card admin-kpi">
        <span class="admin-kpi__label">Variants</span>
        <strong class="admin-kpi__value">{{ $variantStockSummary['total'] }}</strong>
        <span class="admin-kpi__meta">Existing variant records</span>
    </div>
    <div class="admin-card admin-kpi">
        <span class="admin-kpi__label">Low Stock</span>
        <strong class="admin-kpi__value">{{ $variantStockSummary['low_stock'] }}</strong>
        <span class="admin-kpi__meta">1 to 5 units</span>
    </div>
    <div class="admin-card admin-kpi">
        <span class="admin-kpi__label">Out of Stock</span>
        <strong class="admin-kpi__value">{{ $variantStockSummary['out_of_stock'] }}</strong>
        <span class="admin-kpi__meta">0 units</span>
    </div>
    <div class="admin-card admin-kpi">
        <span class="admin-kpi__label">Installment Plans</span>
        <strong class="admin-kpi__value">{{ $variantStockSummary['plans'] }}</strong>
        <span class="admin-kpi__meta">Variant term plans</span>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card__header">
        <div>
            <h3 class="admin-card__title">Product Options</h3>
            <p class="admin-card__copy">Storage and color are configured as reusable option groups so future option types can fit the same structure.</p>
        </div>
    </div>
    <div class="admin-grid admin-grid-2" id="product-option-groups"></div>
</div>

<div class="admin-card">
    <div class="admin-card__header">
        <div><h3 class="admin-card__title">Product Images</h3><p class="admin-card__copy">Upload general images once, or manage a reusable gallery for each color.</p></div>
        <button type="button" class="admin-button admin-button--secondary" id="add-product-image">Add General Image</button>
    </div>
    <h4 class="product-form-card__title">General Images</h4>
    <div class="admin-grid" id="product-images-root"></div>
    <div style="margin-top:20px;"><h4 class="product-form-card__title">Images by Color</h4><p class="admin-help">Color galleries are shared by every storage variant of that color.</p><div class="admin-grid" id="color-images-root"></div></div>
</div>

<div class="admin-card">
    <div class="admin-card__header">
        <div>
            <h3 class="admin-card__title">Variants</h3>
            <p class="admin-card__copy">Generate sellable variants from the current product options. Existing SKU, price, and stock are preserved.</p>
        </div>
        <div class="admin-actions">
            <button type="button" class="admin-button admin-button--secondary" id="generate-variants">Generate Combinations</button>
        </div>
    </div>
    <div class="admin-grid" id="variants-root"></div>
</div>

<div class="admin-card">
    <div class="admin-card__header">
        <div>
            <h3 class="admin-card__title">Description &amp; Specifications</h3>
            <p class="admin-card__copy">Add a detailed description and structured product specifications.</p>
        </div>
        <button type="button" class="admin-button admin-button--secondary" id="add-specification">Add Specification</button>
    </div>
    <div class="admin-field" style="margin-bottom:16px;"><label class="admin-label" for="description">Full Description</label><textarea class="admin-textarea" id="description" name="description">{{ old('description', $product->description) }}</textarea></div>
    <div class="admin-grid" id="specifications-root"></div>
</div>

<div class="admin-modal" id="installment-preview-modal" hidden>
    <div class="admin-modal__backdrop" data-close-preview></div>
    <div class="admin-modal__panel">
        <div class="admin-card__header">
            <div>
                <h3 class="admin-card__title">Installment Preview</h3>
                <p class="admin-card__copy" id="installment-preview-context">Backend schedule preview</p>
            </div>
            <button type="button" class="admin-link" data-close-preview>Close</button>
        </div>
        <div id="installment-preview-body" class="admin-grid"></div>
    </div>
</div>

<div class="admin-actions">
    <button type="submit" class="admin-button" data-loading-label="{{ $submitLabel === 'Create Product' ? 'Creating...' : 'Saving...' }}">{{ $submitLabel }}</button>
    <a href="{{ route('admin.products.index') }}" class="admin-link">Back</a>
</div>

@push('styles')
    <style>
        .product-form-card { border: 1px dashed var(--admin-border-strong); border-radius: 18px; padding: 16px; background: var(--admin-surface-muted); }
        .product-form-card.is-draggable { cursor: move; }
        .product-form-card__title { margin: 0 0 12px; font-size: 16px; font-weight: 700; }
        .product-form-image-preview { width: 88px; height: 88px; border-radius: 18px; border: 1px solid var(--admin-border); background: #fff; object-fit: cover; }
        .product-form-image-placeholder { width: 88px; height: 88px; border-radius: 18px; border: 1px dashed var(--admin-border-strong); display: grid; place-items: center; color: var(--admin-muted); font-size: 12px; background: rgba(255,255,255,0.7); }
        .plan-scope-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 10px; background: rgba(15, 118, 110, 0.12); color: #115e59; font-size: 12px; font-weight: 700; }
        .plan-preview-list { display: grid; gap: 10px; margin-top: 12px; }
        .plan-preview-row { display: flex; justify-content: space-between; gap: 16px; padding: 10px 12px; border-radius: 14px; background: rgba(255,255,255,0.85); border: 1px solid var(--admin-border); }
        .admin-modal[hidden] { display: none; }
        .admin-modal { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 24px; }
        .admin-modal__backdrop { position: absolute; inset: 0; background: rgba(15, 23, 42, 0.56); }
        .admin-modal__panel { position: relative; width: min(720px, 100%); max-height: calc(100vh - 48px); overflow: auto; border-radius: 24px; padding: 24px; background: var(--admin-surface); box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28); }
        .variant-table-wrap { width: 100%; }
        .variant-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .variant-table th, .variant-table td { padding: 10px 8px; border-bottom: 1px solid var(--admin-border); text-align: left; vertical-align: top; }
        .variant-table th { color: var(--admin-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .variant-table .admin-input { min-width: 84px; padding: 8px 10px; }
        .variant-table .variant-stock-column { width: 76px; }
        .variant-table .variant-stock-input { width: 76px; min-width: 76px; padding-inline: 8px; }
        .variant-table .variant-sync-column { width: 38px; text-align: center; }
        .variant-table .variant-sync-select { width: 16px; height: 16px; accent-color: var(--admin-primary); cursor: pointer; }
        .admin-toggle { position: relative; display: inline-flex; align-items: center; cursor: pointer; }
        .admin-toggle__input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .admin-toggle__track { display: inline-flex; align-items: center; width: 42px; height: 24px; padding: 3px; border-radius: 999px; background: #94a3b8; box-shadow: inset 0 1px 2px rgba(15,23,42,.22); transition: background .18s ease, box-shadow .18s ease; }
        .admin-toggle__thumb { width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 2px 5px rgba(15,23,42,.26); transition: transform .18s ease; }
        .admin-toggle__input:checked + .admin-toggle__track { background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-soft)); }
        .admin-toggle__input:checked + .admin-toggle__track .admin-toggle__thumb { transform: translateX(18px); }
        .admin-toggle__input:focus-visible + .admin-toggle__track { outline: 3px solid rgba(37,99,235,.32); outline-offset: 3px; }
        .variant-advanced { display: grid; gap: 12px; min-width: 300px; padding-top: 12px; }
        @media (max-width: 760px) {
            .variant-table, .variant-table tbody, .variant-table tr, .variant-table td { display: block; width: 100%; }
            .variant-table thead { display: none; }
            .variant-table tr { margin-bottom: 12px; padding: 12px; border: 1px solid var(--admin-border); border-radius: 14px; background: var(--admin-surface-muted); }
            .variant-table td { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 10px; align-items: center; border: 0; padding: 6px 0; }
            .variant-table td::before { content: attr(data-label); color: var(--admin-muted); font-size: 12px; font-weight: 700; }
            .variant-table td[data-label="Advanced"] { display: block; }
            .variant-table td[data-label="Advanced"]::before { display: none; }
            .variant-table td.variant-sync-column { display: block; text-align: left; }
            .variant-table td.variant-sync-column::before { display: none; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (() => {
            const optionDefinitions = @json($optionDefinitions);
            const installmentPreviewUrl = @json($product->exists ? route('admin.products.installment-preview', $product) : route('admin.products.installment-preview.create'));
            const state = {
                productOptions: @json($productOptionsState),
                productImages: @json($productImagesState),
                colorImages: @json($colorImagesState),
                variants: @json($variantsState),
                specifications: @json($specificationsState),
                plans: @json($installmentPlansState),
            };

            const roots = {
                productOptions: document.getElementById('product-option-groups'),
                variants: document.getElementById('variants-root'),
                productImages: document.getElementById('product-images-root'),
                colorImages: document.getElementById('color-images-root'),
                specifications: document.getElementById('specifications-root'),
            };
            const previewModal = document.getElementById('installment-preview-modal');
            const previewBody = document.getElementById('installment-preview-body');
            const previewContext = document.getElementById('installment-preview-context');

            const normalizeSignature = (optionValues) => optionValues
                .map((value) => value.id ? `id:${value.id}` : `${String(value.option_slug || '').toLowerCase()}:${String(value.name || '').trim().toLowerCase()}`)
                .filter(Boolean)
                .sort()
                .join('|');

            const createOptionValue = () => ({ id: null, name: '', display_name: '', hex_value: '', swatch_image: '', sort_order: 0, is_active: true });
            const syncProductOptionValues = () => {
                optionDefinitions.forEach((definition, optionIndex) => {
                    const option = state.productOptions.find((entry) => entry.slug === definition.slug);

                    option?.values.forEach((value, valueIndex) => {
                        const inputValue = (field) => document.querySelector(`input[name="product_options[${optionIndex}][values][${valueIndex}][${field}]"]`)?.value;
                        const name = inputValue('name');
                        const displayName = inputValue('display_name');
                        const hexValue = inputValue('hex_value');
                        const swatchImage = inputValue('swatch_image');
                        const sortOrder = inputValue('sort_order');
                        const isActive = document.querySelector(`input[name="product_options[${optionIndex}][values][${valueIndex}][is_active]"][value="1"]`)?.checked;

                        Object.assign(value, {
                            name: name ?? value.name,
                            display_name: displayName ?? value.display_name,
                            hex_value: hexValue ?? value.hex_value,
                            swatch_image: swatchImage ?? value.swatch_image,
                            sort_order: sortOrder ?? value.sort_order,
                            is_active: isActive ?? value.is_active,
                        });
                    });
                });
            };
            const createImage = () => ({ id: null, image_path: '', alt_text: '', sort_order: 0, is_primary: false });
            const createSpecification = () => ({ key: '', value: '' });
            const createVariantKey = () => `variant-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
            const paymentTerms = [3, 6, 9];
            const createPlan = (variant, months) => ({
                id: null,
                scope: 'variant',
                variant_key: variant.client_key,
                product_variant_id: variant.id || null,
                months,
                total_amount: months === 3 ? variant.price || '' : '',
                is_active: true,
            });
            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;');
            const colorHex = (value) => {
                const probe = document.createElement('span');
                probe.style.color = String(value || '');
                if (!probe.style.color) return null;

                document.body.appendChild(probe);
                const channels = window.getComputedStyle(probe).color.match(/\d+/g);
                probe.remove();

                return channels?.length >= 3
                    ? `#${channels.slice(0, 3).map((channel) => Number(channel).toString(16).padStart(2, '0')).join('')}`.toUpperCase()
                    : null;
            };
            const money = (value) => Number(value || 0).toFixed(2);
            const headline = (value) => String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (match) => match.toUpperCase());
            const activeOptionGroups = () => optionDefinitions.map((definition, optionIndex) => {
                const option = state.productOptions.find((entry) => entry.slug === definition.slug);
                const values = (option?.values || [])
                    .map((value, index) => ({
                        id: value.id || null,
                        option_slug: definition.slug,
                        name: document.querySelector(`input[name="product_options[${optionIndex}][values][${index}][name]"]`)?.value || value.name,
                        is_active: document.querySelector(`input[name="product_options[${optionIndex}][values][${index}][is_active]"][value="1"]`)?.checked ?? value.is_active,
                    }))
                    .filter((value) => String(value.name || '').trim() !== '' && value.is_active);

                return { slug: definition.slug, values };
            }).filter((group) => group.values.length > 0);

            state.productOptions = optionDefinitions.map((definition, index) => {
                const existing = state.productOptions.find((option) => option.slug === definition.slug);

                return existing || {
                    id: null,
                    name: definition.name,
                    slug: definition.slug,
                    sort_order: index,
                    is_active: true,
                    values: [],
                };
            });

            state.variants = state.variants.map((variant) => ({
                ...variant,
                client_key: variant.client_key || String(variant.id || createVariantKey()),
                sku_auto: variant.sku_auto !== false,
            }));

            const buildPreview = (path) => path
                ? `<img src="/storage/${path}" alt="" class="product-form-image-preview">`
                : '<div class="product-form-image-placeholder">No image</div>';

            const buildOptionGroups = () => {
                roots.productOptions.innerHTML = optionDefinitions.map((definition, optionIndex) => {
                    const option = state.productOptions.find((entry) => entry.slug === definition.slug);
                    const rows = option.values.map((value, valueIndex) => `
                        <div class="product-form-card" data-option-value-row="${definition.slug}" data-value-index="${valueIndex}">
                            <input type="hidden" name="product_options[${optionIndex}][values][${valueIndex}][id]" value="${value.id || ''}">
                            <div class="admin-grid admin-grid-2">
                                <div class="admin-field">
                                    <label class="admin-label">Value</label>
                                    <input class="admin-input" name="product_options[${optionIndex}][values][${valueIndex}][name]" value="${escapeHtml(value.name || '')}" required>
                                </div>
                                <div class="admin-field">
                                    <label class="admin-label">Display Name</label>
                                    <input class="admin-input" name="product_options[${optionIndex}][values][${valueIndex}][display_name]" value="${escapeHtml(value.display_name || '')}">
                                </div>
                                ${definition.supports_hex ? `
                                    <div class="admin-field">
                                        <label class="admin-label">Color</label>
                                        <div class="admin-inline" style="gap:8px; align-items:center;">
                                            <input type="color" value="${escapeHtml(colorHex(value.hex_value) || '#000000')}" data-color-picker>
                                            <input class="admin-input" value="${escapeHtml(value.hex_value || '')}" placeholder="#2563EB or blue" data-color-value>
                                            <input type="hidden" name="product_options[${optionIndex}][values][${valueIndex}][hex_value]" value="${escapeHtml(colorHex(value.hex_value) || '')}" data-color-hex>
                                        </div>
                                        <p class="admin-help" style="margin:6px 0 0;">Choose a color, or enter a hex code or color name such as blue or gray.</p>
                                    </div>
                                ` : ''}
                                ${definition.supports_swatch ? `
                                    <div class="admin-field">
                                        <label class="admin-label">Swatch Image</label>
                                        <input class="admin-file-input" type="file" name="product_options[${optionIndex}][values][${valueIndex}][swatch_upload]" accept="image/*">
                                        <input type="hidden" name="product_options[${optionIndex}][values][${valueIndex}][swatch_image]" value="${escapeHtml(value.swatch_image || '')}">
                                        ${value.swatch_image ? `<div class="admin-help">Saved swatch: ${escapeHtml(value.swatch_image)}</div>` : ''}
                                    </div>
                                ` : ''}
                                <div class="admin-field">
                                    <label class="admin-label">Sort Order</label>
                                    <input class="admin-input" type="number" min="0" name="product_options[${optionIndex}][values][${valueIndex}][sort_order]" value="${value.sort_order ?? valueIndex}">
                                </div>
                            </div>
                            <div class="admin-inline" style="justify-content:space-between; margin-top:12px;">
                                <label style="display:flex; gap:10px; align-items:center;">
                                    <input type="hidden" name="product_options[${optionIndex}][values][${valueIndex}][is_active]" value="0">
                                    <input type="checkbox" name="product_options[${optionIndex}][values][${valueIndex}][is_active]" value="1" ${value.is_active === false ? '' : 'checked'}>
                                    <span>Active</span>
                                </label>
                                <button type="button" class="admin-link" data-remove-option-value="${definition.slug}" data-value-index="${valueIndex}">Remove</button>
                            </div>
                        </div>
                    `).join('');

                    return `
                        <section class="product-form-card">
                            <input type="hidden" name="product_options[${optionIndex}][id]" value="${option.id || ''}">
                            <input type="hidden" name="product_options[${optionIndex}][name]" value="${escapeHtml(option.name)}">
                            <input type="hidden" name="product_options[${optionIndex}][slug]" value="${escapeHtml(option.slug)}">
                            <input type="hidden" name="product_options[${optionIndex}][sort_order]" value="${option.sort_order ?? optionIndex}">
                            <div class="admin-card__header" style="margin-bottom:12px;">
                                <div>
                                    <h4 class="product-form-card__title">${escapeHtml(option.name)}</h4>
                                    <p class="admin-help" style="margin:0;">${definition.slug === 'storage' ? 'Examples: 128 GB, 256 GB, 512 GB.' : 'Add a color name and optionally a hex value or swatch image.'}</p>
                                </div>
                                <button type="button" class="admin-button admin-button--secondary" data-add-option-value="${definition.slug}">Add Value</button>
                            </div>
                            <div class="admin-inline" style="margin-bottom:12px;">
                                <label style="display:flex; gap:10px; align-items:center;">
                                    <input type="hidden" name="product_options[${optionIndex}][is_active]" value="0">
                                    <input type="checkbox" name="product_options[${optionIndex}][is_active]" value="1" ${option.is_active === false ? '' : 'checked'}>
                                    <span>Option group active</span>
                                </label>
                            </div>
                            <div class="admin-grid">${rows || '<div class="admin-help">No values added yet.</div>'}</div>
                        </section>
                    `;
                }).join('');

                roots.productOptions.querySelectorAll('[data-add-option-value]').forEach((button) => {
                    button.addEventListener('click', () => {
                        syncProductOptionValues();
                        const option = state.productOptions.find((entry) => entry.slug === button.dataset.addOptionValue);
                        option.values.push(createOptionValue());
                        buildOptionGroups();
                        buildColorImages();
                    });
                });

                roots.productOptions.querySelectorAll('[data-remove-option-value]').forEach((button) => {
                    button.addEventListener('click', () => {
                        syncProductOptionValues();
                        const option = state.productOptions.find((entry) => entry.slug === button.dataset.removeOptionValue);
                        option.values.splice(Number(button.dataset.valueIndex), 1);
                        buildOptionGroups();
                        buildColorImages();
                    });
                });

                roots.productOptions.querySelectorAll('input[name*="[values]"][name$="[name]"]').forEach((input) => {
                    input.addEventListener('change', () => {
                        syncProductOptionValues();
                        syncVariantOptionNamesFromOptions();
                        autoFillVariantSkus();
                        buildColorImages();
                        buildVariants();
                    });
                });

                roots.productOptions.querySelectorAll('[data-color-value]').forEach((input) => {
                    const field = input.closest('.admin-field');
                    const picker = field?.querySelector('[data-color-picker]');
                    const hidden = field?.querySelector('[data-color-hex]');

                    const updateColor = (value) => {
                        const hex = colorHex(value);
                        if (!hex) return;
                        input.value = hex;
                        picker.value = hex;
                        hidden.value = hex;
                    };

                    input.addEventListener('change', () => updateColor(input.value));
                    picker?.addEventListener('input', () => updateColor(picker.value));
                });
            };

            const buildProductImages = () => {
                roots.productImages.innerHTML = state.productImages.map((image, index) => `
                    <div class="product-form-card is-draggable" draggable="true" data-product-image-index="${index}">
                        <input type="hidden" name="product_images[${index}][id]" value="${image.id || ''}">
                        <input type="hidden" name="product_images[${index}][image_path]" value="${escapeHtml(image.image_path || '')}">
                        <div class="admin-inline" style="align-items:flex-start; justify-content:space-between;">
                            <div class="admin-inline" style="align-items:flex-start;">
                                <div data-image-preview>${buildPreview(image.image_path || '')}</div>
                                <div class="admin-grid" style="min-width:min(100%, 420px);">
                                    <div class="admin-field">
                                        <label class="admin-label">Upload Image</label>
                                        <input class="admin-file-input" type="file" name="product_images[${index}][upload]" accept="image/*">
                                        <div class="admin-help">Accepted image files up to 5 MB.</div>
                                    </div>
                                    <div class="admin-field">
                                        <label class="admin-label">Alt Text</label>
                                        <input class="admin-input" name="product_images[${index}][alt_text]" value="${escapeHtml(image.alt_text || '')}">
                                    </div>
                                    <div class="admin-field">
                                        <label class="admin-label">Sort Order</label>
                                        <input class="admin-input" type="number" min="0" name="product_images[${index}][sort_order]" value="${index}">
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="admin-link" data-remove-product-image="${index}">Remove</button>
                        </div>
                        <div class="admin-inline" style="margin-top:12px;">
                            <label style="display:flex; gap:10px; align-items:center;">
                                <input type="hidden" name="product_images[${index}][is_primary]" value="0">
                                <input type="checkbox" name="product_images[${index}][is_primary]" value="1" ${image.is_primary ? 'checked' : ''} data-primary-product-image="${index}">
                                <span>Primary image</span>
                            </label>
                        </div>
                    </div>
                `).join('');

                bindImageInputs(roots.productImages, 'product_images');
                bindProductImageOrdering();

                roots.productImages.querySelectorAll('[data-remove-product-image]').forEach((button) => {
                    button.addEventListener('click', () => {
                        state.productImages.splice(Number(button.dataset.removeProductImage), 1);
                        buildProductImages();
                    });
                });

                roots.productImages.querySelectorAll('[data-primary-product-image]').forEach((radio) => {
                    radio.addEventListener('change', () => {
                        state.productImages = state.productImages.map((image, index) => ({ ...image, is_primary: index === Number(radio.dataset.primaryProductImage) }));
                        buildProductImages();
                    });
                });
            };

            const colorValuesForGallery = () => {
                const color = state.productOptions.find((option) => option.slug === 'color');
                return (color?.values || []).filter((value) => value.is_active !== false && String(value.name || '').trim() !== '');
            };

            const syncColorImageInputs = () => {
                state.colorImages.forEach((gallery, galleryIndex) => (gallery.images || []).forEach((image, imageIndex) => {
                    const value = (field) => document.querySelector(`input[name="color_images[${galleryIndex}][images][${imageIndex}][${field}]"]`)?.value;
                    const upload = document.querySelector(`input[data-color-image-upload="${galleryIndex}:${imageIndex}"]`)?.files?.[0];
                    image.alt_text = value('alt_text') ?? image.alt_text;
                    image.sort_order = value('sort_order') ?? image.sort_order;
                    image.is_primary = document.querySelector(`input[name="color_images[${galleryIndex}][images][${imageIndex}][is_primary]"][value="1"]`)?.checked ?? image.is_primary;
                    if (upload) image.upload = upload;
                }));
            };

            const restoreColorImageUploads = () => {
                state.colorImages.forEach((gallery, galleryIndex) => (gallery.images || []).forEach((image, imageIndex) => {
                    if (!image.upload) return;

                    const input = roots.colorImages.querySelector(`input[data-color-image-upload="${galleryIndex}:${imageIndex}"]`);
                    const preview = input?.closest('.product-form-card')?.querySelector('[data-image-preview]');
                    if (!input || !preview || typeof DataTransfer === 'undefined') return;

                    const transfer = new DataTransfer();
                    transfer.items.add(image.upload);
                    input.files = transfer.files;
                    preview.innerHTML = `<img src="${URL.createObjectURL(image.upload)}" alt="" class="product-form-image-preview">`;
                }));
            };

            const buildColorImages = () => {
                syncProductOptionValues();
                const colors = colorValuesForGallery();
                state.colorImages = colors.map((color) => state.colorImages.find((gallery) =>
                    (color.id && Number(gallery.option_value_id) === Number(color.id)) || (!color.id && gallery.option_value_name === color.name)
                ) || ({ option_value_id: color.id || null, option_value_name: color.name, images: [] }));

                roots.colorImages.innerHTML = state.colorImages.length ? state.colorImages.map((gallery, galleryIndex) => `
                    <section class="product-form-card">
                        <input type="hidden" name="color_images[${galleryIndex}][option_value_id]" value="${gallery.option_value_id || ''}">
                        <input type="hidden" name="color_images[${galleryIndex}][option_value_name]" value="${escapeHtml(gallery.option_value_name || '')}">
                        <div class="admin-card__header" style="margin-bottom:12px;"><div><h5 class="product-form-card__title">${escapeHtml(gallery.option_value_name || 'Color')}</h5><p class="admin-help" style="margin:0;">Shared by every variant with this color.</p></div><button type="button" class="admin-button admin-button--secondary" data-add-color-image="${galleryIndex}">Add Image</button></div>
                        <div class="admin-grid admin-grid-2">${(gallery.images || []).map((image, imageIndex) => `
                            <div class="product-form-card">
                                <input type="hidden" name="color_images[${galleryIndex}][images][${imageIndex}][id]" value="${image.id || ''}">
                                <input type="hidden" name="color_images[${galleryIndex}][images][${imageIndex}][image_path]" value="${escapeHtml(image.image_path || '')}">
                                <div class="admin-inline" style="align-items:flex-start; justify-content:space-between;"><div class="admin-inline" style="align-items:flex-start;"><div data-image-preview>${buildPreview(image.image_path || '')}</div><div class="admin-grid"><div class="admin-field"><label class="admin-label">Upload Image</label><input class="admin-file-input" type="file" name="color_images[${galleryIndex}][images][${imageIndex}][upload]" data-color-image-upload="${galleryIndex}:${imageIndex}" accept="image/*"></div><div class="admin-field"><label class="admin-label">Alt Text</label><input class="admin-input" name="color_images[${galleryIndex}][images][${imageIndex}][alt_text]" value="${escapeHtml(image.alt_text || '')}"></div><div class="admin-field"><label class="admin-label">Sort Order</label><input class="admin-input" type="number" min="0" name="color_images[${galleryIndex}][images][${imageIndex}][sort_order]" value="${image.sort_order ?? imageIndex}"></div></div></div><button type="button" class="admin-link" data-remove-color-image="${galleryIndex}:${imageIndex}">Remove</button></div>
                                <label style="display:flex; gap:10px; align-items:center; margin-top:12px;"><input type="hidden" name="color_images[${galleryIndex}][images][${imageIndex}][is_primary]" value="0"><input type="checkbox" name="color_images[${galleryIndex}][images][${imageIndex}][is_primary]" value="1" ${image.is_primary ? 'checked' : ''} data-primary-color-image="${galleryIndex}:${imageIndex}"><span>Primary image</span></label>
                            </div>`).join('') || '<div class="admin-help">No images yet.</div>'}</div>
                    </section>`).join('') : '<div class="admin-help">Add an active color value to manage its gallery.</div>';

                roots.colorImages.querySelectorAll('[data-add-color-image]').forEach((button) => button.addEventListener('click', () => {
                    syncColorImageInputs(); state.colorImages[Number(button.dataset.addColorImage)].images.push(createImage()); buildColorImages();
                }));
                roots.colorImages.querySelectorAll('[data-remove-color-image]').forEach((button) => button.addEventListener('click', () => {
                    syncColorImageInputs(); const [galleryIndex, imageIndex] = button.dataset.removeColorImage.split(':').map(Number); state.colorImages[galleryIndex].images.splice(imageIndex, 1); buildColorImages();
                }));
                roots.colorImages.querySelectorAll('[data-primary-color-image]').forEach((button) => button.addEventListener('change', () => {
                    syncColorImageInputs(); const [galleryIndex, imageIndex] = button.dataset.primaryColorImage.split(':').map(Number); state.colorImages[galleryIndex].images = state.colorImages[galleryIndex].images.map((image, index) => ({ ...image, is_primary: index === imageIndex })); buildColorImages();
                }));
                restoreColorImageUploads();
                bindImageInputs(roots.colorImages);
            };

            const buildSpecifications = () => {
                roots.specifications.innerHTML = state.specifications.map((specification, index) => `
                    <div class="product-form-card">
                        <div class="admin-grid admin-grid-2">
                            <div class="admin-field">
                                <label class="admin-label">Specification</label>
                                <input class="admin-input" name="specifications[${index}][key]" value="${escapeHtml(specification.key || '')}" placeholder="Display">
                            </div>
                            <div class="admin-field">
                                <label class="admin-label">Value</label>
                                <input class="admin-input" name="specifications[${index}][value]" value="${escapeHtml(specification.value || '')}" placeholder="6.7 inches">
                            </div>
                        </div>
                        <div class="admin-inline" style="justify-content:flex-end; margin-top:12px;">
                            <button type="button" class="admin-link" data-remove-specification="${index}">Remove</button>
                        </div>
                    </div>
                `).join('');

                roots.specifications.querySelectorAll('[data-remove-specification]').forEach((button) => {
                    button.addEventListener('click', () => {
                        syncSpecifications();
                        state.specifications.splice(Number(button.dataset.removeSpecification), 1);
                        if (state.specifications.length === 0) {
                            state.specifications.push(createSpecification());
                        }
                        buildSpecifications();
                    });
                });
            };

            const syncSpecifications = () => {
                state.specifications.forEach((specification, index) => {
                    const value = (field) => document.querySelector(`input[name="specifications[${index}][${field}]"]`)?.value;
                    specification.key = value('key') ?? specification.key;
                    specification.value = value('value') ?? specification.value;
                });
            };

            const buildPlans = () => syncPlanVariantTargets();

            const variantLabel = (variant) => variant.option_values.map((value) => value.name).join(' / ') || 'Variant';

            const skuPart = (value) => String(value || '')
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

            const suggestedSku = (variant) => {
                const productName = document.getElementById('name')?.value;
                const storage = variant.option_values?.find((value) => value.option_slug === 'storage')?.name;
                const color = variant.option_values?.find((value) => value.option_slug === 'color')?.name;

                if (!productName || !storage || !color) return '';

                return [productName, storage, color].map(skuPart).filter(Boolean).join('-');
            };

            const autoFillVariantSkus = () => {
                state.variants.forEach((variant, variantIndex) => {
                    if (variant.sku_auto === false) return;

                    const sku = suggestedSku(variant);
                    if (!sku) return;

                    variant.sku = sku;
                    const input = document.querySelector(`input[name="variants[${variantIndex}][sku]"]`);
                    if (input) input.value = sku;
                });
            };

            const planMatchesVariant = (plan, variant) => plan.variant_key === variant.client_key
                || (Boolean(variant.id) && Boolean(plan.product_variant_id) && String(plan.product_variant_id) === String(variant.id));

            const syncVariantInputs = () => {
                state.variants.forEach((variant, variantIndex) => {
                    const value = (field) => document.querySelector(`input[name="variants[${variantIndex}][${field}]"]`)?.value;
                    variant.sku = value('sku') ?? variant.sku;
                    variant.barcode = value('barcode') ?? variant.barcode;
                    variant.price = value('price') ?? variant.price;
                    variant.compare_at_price = value('compare_at_price') ?? variant.compare_at_price;
                    variant.cost_price = value('cost_price') ?? variant.cost_price;
                    variant.stock_quantity = value('stock_quantity') ?? variant.stock_quantity;
                    variant.is_active = document.querySelector(`input[name="variants[${variantIndex}][is_active]"][value="1"]`)?.checked ?? variant.is_active;
                });
            };

            const buildVariantImages = (variant, variantIndex) => (variant.images || []).map((image, imageIndex) => `
                <div class="product-form-card">
                    <input type="hidden" name="variants[${variantIndex}][images][${imageIndex}][id]" value="${image.id || ''}">
                    <input type="hidden" name="variants[${variantIndex}][images][${imageIndex}][image_path]" value="${escapeHtml(image.image_path || '')}">
                    <div class="admin-inline" style="align-items:flex-start; justify-content:space-between;">
                        <div class="admin-inline" style="align-items:flex-start;">
                            <div data-image-preview>${buildPreview(image.image_path || '')}</div>
                            <div class="admin-grid" style="min-width:min(100%, 420px);">
                                <div class="admin-field">
                                    <label class="admin-label">Variant Image</label>
                                    <input class="admin-file-input" type="file" name="variants[${variantIndex}][images][${imageIndex}][upload]" accept="image/*">
                                </div>
                                <div class="admin-field">
                                    <label class="admin-label">Alt Text</label>
                                    <input class="admin-input" name="variants[${variantIndex}][images][${imageIndex}][alt_text]" value="${escapeHtml(image.alt_text || '')}">
                                </div>
                                <div class="admin-field">
                                    <label class="admin-label">Sort Order</label>
                                    <input class="admin-input" type="number" min="0" name="variants[${variantIndex}][images][${imageIndex}][sort_order]" value="${imageIndex}">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="admin-link" data-remove-variant-image="${variantIndex}:${imageIndex}">Remove</button>
                    </div>
                    <div class="admin-inline" style="margin-top:12px;">
                        <label style="display:flex; gap:10px; align-items:center;">
                            <input type="hidden" name="variants[${variantIndex}][images][${imageIndex}][is_primary]" value="0">
                            <input type="checkbox" name="variants[${variantIndex}][images][${imageIndex}][is_primary]" value="1" ${image.is_primary ? 'checked' : ''} data-primary-variant-image="${variantIndex}:${imageIndex}">
                            <span>Primary image</span>
                        </label>
                    </div>
                </div>
            `).join('');

            const installmentCell = (variant, term) => {
                const index = state.plans.findIndex((plan) => plan.months === term && planMatchesVariant(plan, variant));
                const plan = state.plans[index];

                return `<td data-label="${term} Payments Total"><input type="hidden" name="installment_plans[${index}][id]" value="${plan.id || ''}"><input type="hidden" name="installment_plans[${index}][scope]" value="variant"><input type="hidden" name="installment_plans[${index}][variant_key]" value="${escapeHtml(variant.client_key || '')}"><input type="hidden" name="installment_plans[${index}][product_variant_id]" value="${escapeHtml(variant.id || '')}"><input type="hidden" name="installment_plans[${index}][months]" value="${term}"><input type="hidden" name="installment_plans[${index}][is_active]" value="1"><input class="admin-input" type="number" step="0.01" min="0.01" required name="installment_plans[${index}][total_amount]" data-plan-total="${index}" data-variant-sync-field="plan-${term}" value="${escapeHtml(plan.total_amount || '')}" aria-label="${term} payments total for ${escapeHtml(variantLabel(variant))}"></td>`;
            };

            const buildVariants = () => {
                syncPlanVariantTargets();
                if (state.variants.length === 0) {
                    roots.variants.innerHTML = '<div class="admin-empty-state"><h3>No variants yet</h3><p>Add at least one active storage or color value, then generate combinations to create sellable variants.</p></div>';
                    buildPlans();
                    return;
                }

                roots.variants.innerHTML = `<div class="variant-table-wrap"><table class="variant-table"><thead><tr><th class="variant-sync-column"></th><th>Combination</th><th>SKU</th><th>Barcode</th><th>3 Payments Total</th><th>6 Payments Total</th><th>9 Payments Total</th><th>Compare At</th><th>Unit Cost</th><th class="variant-stock-column">Stock</th><th>Active</th><th>Advanced</th></tr></thead><tbody>${state.variants.map((variant, variantIndex) => `
                    <tr data-variant-record="${variantIndex}">
                        <td class="variant-sync-column"><input class="variant-sync-select" type="checkbox" data-variant-sync-select="${variantIndex}" aria-label="Select variant"></td>
                        <td data-label="Combination"><strong>${escapeHtml(variantLabel(variant))}</strong><input type="hidden" name="variants[${variantIndex}][id]" value="${variant.id || ''}"><input type="hidden" name="variants[${variantIndex}][client_key]" value="${escapeHtml(variant.client_key || '')}">${variant.option_values.map((value, valueIndex) => `<input type="hidden" name="variants[${variantIndex}][option_value_ids][]" value="${value.id || ''}"><input type="hidden" name="variants[${variantIndex}][option_values][${valueIndex}][id]" value="${value.id || ''}"><input type="hidden" name="variants[${variantIndex}][option_values][${valueIndex}][option_slug]" value="${escapeHtml(value.option_slug || '')}"><input type="hidden" name="variants[${variantIndex}][option_values][${valueIndex}][name]" value="${escapeHtml(value.name || '')}">`).join('')}</td>
                        <td data-label="SKU"><input class="admin-input" name="variants[${variantIndex}][sku]" data-variant-sync-field="sku" value="${escapeHtml(variant.sku || '')}" required></td>
                        <td data-label="Barcode"><input class="admin-input" name="variants[${variantIndex}][barcode]" data-variant-sync-field="barcode" value="${escapeHtml(variant.barcode || '')}" inputmode="numeric" autocomplete="off" aria-label="Barcode for ${escapeHtml(variantLabel(variant))}"></td>
                        ${installmentCell(variant, 3)}
                        ${installmentCell(variant, 6)}
                        ${installmentCell(variant, 9)}
                        <td data-label="Compare At"><input class="admin-input" type="number" step="0.01" min="0" name="variants[${variantIndex}][compare_at_price]" data-variant-sync-field="compare_at_price" value="${escapeHtml(variant.compare_at_price || '')}"></td>
                        <td data-label="Unit Cost"><input class="admin-input" type="number" step="0.01" min="0" name="variants[${variantIndex}][cost_price]" data-variant-sync-field="cost_price" value="${escapeHtml(variant.cost_price ?? '')}" placeholder="Unknown" aria-label="Unit cost for ${escapeHtml(variantLabel(variant))}"></td>
                        <td data-label="Stock" class="variant-stock-column"><input class="admin-input variant-stock-input" type="number" min="0" name="variants[${variantIndex}][stock_quantity]" data-variant-sync-field="stock_quantity" value="${escapeHtml(variant.stock_quantity ?? 0)}" required></td>
                        <td data-label="Active"><label class="admin-toggle" title="${variant.is_active === false ? 'Activate variant' : 'Deactivate variant'}"><input type="hidden" name="variants[${variantIndex}][is_active]" value="0"><input class="admin-toggle__input" type="checkbox" name="variants[${variantIndex}][is_active]" data-variant-sync-field="is_active" value="1" ${variant.is_active === false ? '' : 'checked'}><span class="admin-toggle__track" aria-hidden="true"><span class="admin-toggle__thumb"></span></span><span class="sr-only">Active variant</span></label></td>
                        <td data-label="Advanced"><details><summary>Advanced</summary><div class="variant-advanced"><p class="admin-help">Optional. Use images here only when this exact variant must override its color gallery.</p><button type="button" class="admin-button admin-button--secondary" data-add-variant-image="${variantIndex}">Override Color Gallery</button><div class="admin-grid" data-variant-images-root="${variantIndex}">${buildVariantImages(variant, variantIndex) || '<div class="admin-help">Color gallery will be used.</div>'}</div><button type="button" class="admin-link" data-remove-variant="${variantIndex}">Remove variant</button></div></details></td>
                    </tr>`).join('')}</tbody></table></div>`;

                const updateStateFromVariantField = (input, variantIndex) => {
                    const field = input.dataset.variantSyncField;
                    if (field.startsWith('plan-')) {
                        const plan = state.plans[Number(input.dataset.planTotal)];
                        plan.total_amount = input.value;
                        if (plan.months === 3) state.variants[variantIndex].price = input.value;
                        return;
                    }

                    state.variants[variantIndex][field] = input.type === 'checkbox' ? input.checked : input.value;
                    if (field === 'sku') state.variants[variantIndex].sku_auto = false;
                };

                roots.variants.querySelectorAll('[data-variant-sync-field]').forEach((input) => {
                    const mirror = () => {
                        const sourceRow = input.closest('[data-variant-record]');
                        const sourceIndex = Number(sourceRow.dataset.variantRecord);
                        updateStateFromVariantField(input, sourceIndex);

                        const selectedRows = [...roots.variants.querySelectorAll('[data-variant-sync-select]:checked')]
                            .map((checkbox) => Number(checkbox.dataset.variantSyncSelect));
                        if (selectedRows.length < 2 || !selectedRows.includes(sourceIndex)) return;

                        selectedRows.filter((index) => index !== sourceIndex).forEach((targetIndex) => {
                            const target = roots.variants.querySelector(`[data-variant-record="${targetIndex}"] [data-variant-sync-field="${input.dataset.variantSyncField}"]`);
                            if (!target) return;
                            if (input.type === 'checkbox') target.checked = input.checked;
                            else target.value = input.value;
                            updateStateFromVariantField(target, targetIndex);
                        });
                    };

                    input.addEventListener(input.type === 'checkbox' ? 'change' : 'input', mirror);
                });
                roots.variants.querySelectorAll('[data-remove-variant]').forEach((button) => {
                    button.addEventListener('click', () => {
                        syncVariantInputs();
                        const index = Number(button.dataset.removeVariant);
                        const variant = state.variants[index];
                        const hasStock = Number(variant.stock_quantity || 0) > 0;

                        if (hasStock && ! window.confirm('This variant has stock. Remove it from the submitted combinations? The existing record will be kept inactive.')) {
                            return;
                        }

                        state.variants.splice(index, 1);
                        buildVariants();
                    });
                });

                roots.variants.querySelectorAll('[data-add-variant-image]').forEach((button) => {
                    button.addEventListener('click', () => {
                        syncVariantInputs();
                        const variant = state.variants[Number(button.dataset.addVariantImage)];
                        variant.images = variant.images || [];
                        variant.images.push(createImage());
                        buildVariants();
                    });
                });

                roots.variants.querySelectorAll('[data-remove-variant-image]').forEach((button) => {
                    button.addEventListener('click', () => {
                        syncVariantInputs();
                        const [variantIndex, imageIndex] = button.dataset.removeVariantImage.split(':').map(Number);
                        state.variants[variantIndex].images.splice(imageIndex, 1);
                        buildVariants();
                    });
                });

                roots.variants.querySelectorAll('[data-primary-variant-image]').forEach((radio) => {
                    radio.addEventListener('change', () => {
                        syncVariantInputs();
                        const [variantIndex, imageIndex] = radio.dataset.primaryVariantImage.split(':').map(Number);
                        state.variants[variantIndex].images = state.variants[variantIndex].images.map((image, index) => ({
                            ...image,
                            is_primary: index === imageIndex,
                        }));
                        buildVariants();
                    });
                });

                bindImageInputs(roots.variants, 'variants');
                buildPlans();
            };

            const bindImageInputs = (root) => {
                root.querySelectorAll('input[type="file"]').forEach((input) => {
                    input.addEventListener('change', () => {
                        const file = input.files?.[0];
                        const preview = input.closest('.product-form-card')?.querySelector('[data-image-preview]');

                        if (!preview || !file) {
                            return;
                        }

                        preview.innerHTML = `<img src="${URL.createObjectURL(file)}" alt="" class="product-form-image-preview">`;
                    });
                });
            };

            const currentOptionValueMap = () => optionDefinitions.reduce((map, definition, optionIndex) => {
                const option = state.productOptions.find((entry) => entry.slug === definition.slug);
                const values = (option?.values || []).map((value, valueIndex) => ({
                    id: value.id || null,
                    option_slug: definition.slug,
                    name: document.querySelector(`input[name="product_options[${optionIndex}][values][${valueIndex}][name]"]`)?.value || value.name,
                    is_active: document.querySelector(`input[name="product_options[${optionIndex}][values][${valueIndex}][is_active]"][value="1"]`)?.checked ?? value.is_active,
                }));

                values.forEach((value) => {
                    if (value.id) {
                        map[`id:${value.id}`] = value;
                    }

                    map[`slug:${value.option_slug}:${String(value.name).trim().toLowerCase()}`] = value;
                });

                return map;
            }, {});

            const syncVariantOptionNamesFromOptions = () => {
                const lookup = currentOptionValueMap();

                state.variants = state.variants.map((variant) => ({
                    ...variant,
                    option_values: (variant.option_values || []).map((value) => {
                        const byId = value.id ? lookup[`id:${value.id}`] : null;
                        const bySlug = lookup[`slug:${value.option_slug}:${String(value.name || '').trim().toLowerCase()}`];
                        const match = byId || bySlug;

                        return match ? { ...value, name: match.name, option_slug: match.option_slug } : value;
                    }),
                }));
            };

            const bindProductImageOrdering = () => {
                let draggedIndex = null;

                roots.productImages.querySelectorAll('[data-product-image-index]').forEach((row) => {
                    row.addEventListener('dragstart', () => {
                        draggedIndex = Number(row.dataset.productImageIndex);
                    });

                    row.addEventListener('dragover', (event) => event.preventDefault());
                    row.addEventListener('drop', (event) => {
                        event.preventDefault();
                        const dropIndex = Number(row.dataset.productImageIndex);

                        if (draggedIndex === null || draggedIndex === dropIndex) {
                            return;
                        }

                        const [moved] = state.productImages.splice(draggedIndex, 1);
                        state.productImages.splice(dropIndex, 0, moved);
                        draggedIndex = null;
                        buildProductImages();
                    });
                });
            };

            const generateVariants = () => {
                syncProductOptionValues();
                syncVariantInputs();
                syncVariantOptionNamesFromOptions();
                const optionGroups = activeOptionGroups();

                if (optionGroups.length === 0) {
                    roots.variants.innerHTML = '<div class="admin-empty-state"><h3>No active option values</h3><p>Add active storage or color values before generating combinations.</p></div>';
                    return;
                }

                const combinations = optionGroups.reduce((carry, group) => {
                    if (carry.length === 0) {
                        return group.values.map((value) => [value]);
                    }

                    return carry.flatMap((prefix) => group.values.map((value) => [...prefix, value]));
                }, []);

                const generatedSignatures = new Set(combinations.map((combination) => normalizeSignature(combination)));
                const existingBySignature = new Map(state.variants.map((variant) => [normalizeSignature(variant.option_values || []), variant]));
                const rebuiltVariants = combinations.map((combination) => {
                    const signature = normalizeSignature(combination);
                    const existing = existingBySignature.get(signature);

                    if (existing) {
                        return {
                            ...existing,
                            option_values: combination,
                        };
                    }

                    return {
                        id: null,
                        client_key: createVariantKey(),
                        sku: '',
                        sku_auto: true,
                        price: '',
                        compare_at_price: '',
                        cost_price: '',
                        stock_quantity: 0,
                        is_active: true,
                        option_values: combination,
                        images: [],
                    };
                });

                const retainedLegacyVariants = state.variants.filter((variant) => {
                    const signature = normalizeSignature(variant.option_values || []);

                    return !generatedSignatures.has(signature) && (variant.id || Number(variant.stock_quantity || 0) > 0);
                });

                state.variants = [...rebuiltVariants, ...retainedLegacyVariants];

                autoFillVariantSkus();
                buildVariants();
            };

            const syncPlanVariantTargets = () => {
                state.plans = state.plans.filter((plan) => plan.scope === 'variant');
                state.variants.forEach((variant) => paymentTerms.forEach((months) => {
                    const plan = state.plans.find((entry) => entry.months === months && planMatchesVariant(entry, variant));
                    if (plan) {
                        plan.variant_key = variant.client_key;
                        plan.product_variant_id = variant.id || null;
                    } else {
                        state.plans.push(createPlan(variant, months));
                    }
                }));
                const variantKeys = new Set(state.variants.map((variant) => variant.client_key));
                state.plans = state.plans.filter((plan) => variantKeys.has(plan.variant_key));
            };

            const closePreview = () => {
                previewModal.hidden = true;
                previewBody.innerHTML = '';
            };

            const renderPreview = (plan, response) => {
                const preview = response.preview;
                const planLabel = plan.scope === 'variant'
                    ? state.variants.find((variant) => variant.client_key === plan.variant_key)?.sku || 'Variant-specific plan'
                    : 'All variants';

                previewContext.textContent = `Monthly schedule for ${plan.months} payments · ${planLabel}`;
                previewBody.innerHTML = `
                    <div class="admin-grid admin-grid-2">
                        <div class="admin-card admin-card--tight">
                            <strong>First payment today</strong>
                            <div>${money(preview.amount_due_now)}</div>
                        </div>
                        <div class="admin-card admin-card--tight">
                            <strong>Future payments</strong>
                            <div>${preview.future_payment_count}</div>
                        </div>
                        <div class="admin-card admin-card--tight">
                            <strong>Total amount</strong>
                            <div>${money(preview.total_amount)}</div>
                        </div>
                        <div class="admin-card admin-card--tight">
                            <strong>Each payment</strong>
                            <div>${money(preview.installment_amount)}</div>
                        </div>
                    </div>
                    <div class="plan-preview-list">
                        ${preview.future_installments.map((payment) => `
                            <div class="plan-preview-row">
                                <span>Payment ${payment.sequence} · ${payment.due_date}</span>
                                <strong>${money(payment.amount)}</strong>
                            </div>
                        `).join('')}
                    </div>
                `;
                previewModal.hidden = false;
            };

            const previewPlan = async (index) => {
                syncVariantOptionNamesFromOptions();
                syncPlanVariantTargets();

                const plan = state.plans[index];
                const payload = {
                    scope: plan.scope,
                    variant_key: plan.variant_key,
                    product_variant_id: plan.product_variant_id,
                    number_of_payments: plan.months,
                    total_amount: Number(document.querySelector(`input[name="installment_plans[${index}][total_amount]"]`)?.value || plan.total_amount || 0),
                    interval_type: 'monthly',
                    variants: state.variants.map((variant, variantIndex) => ({
                        id: variant.id,
                        client_key: variant.client_key,
                        sku: document.querySelector(`input[name="variants[${variantIndex}][sku]"]`)?.value || variant.sku || '',
                        price: Number(document.querySelector(`input[name="variants[${variantIndex}][price]"]`)?.value || variant.price || 0),
                    })),
                };

                const response = await fetch(installmentPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok) {
                    window.alert(data.message || 'Unable to preview this plan.');
                    return;
                }

                state.plans[index] = {
                    ...state.plans[index],
                    months: payload.number_of_payments,
                    total_amount: payload.total_amount,
                };

                renderPreview(plan, data);
            };

            document.getElementById('add-product-image')?.addEventListener('click', () => {
                state.productImages.push(createImage());
                buildProductImages();
            });

            document.getElementById('add-specification')?.addEventListener('click', () => {
                syncSpecifications();
                state.specifications.push(createSpecification());
                buildSpecifications();
            });

            document.getElementById('generate-variants')?.addEventListener('click', generateVariants);
            document.getElementById('name')?.addEventListener('input', autoFillVariantSkus);
            document.querySelector('form[data-loading-form]')?.addEventListener('submit', () => {
                syncProductOptionValues();
                syncColorImageInputs();
                syncVariantInputs();
                syncSpecifications();
                syncVariantOptionNamesFromOptions();
                syncPlanVariantTargets();
            });
            previewModal?.querySelectorAll('[data-close-preview]').forEach((button) => button.addEventListener('click', closePreview));

            buildOptionGroups();
            buildProductImages();
            buildColorImages();
            buildSpecifications();
            buildPlans();
            buildVariants();
        })();
    </script>
@endpush
