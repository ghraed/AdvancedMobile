<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\InstallmentCalculatorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product
            ? $this->user()?->can('update', $product) ?? false
            : $this->user()?->can('create', Product::class) ?? false;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'brand' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'is_featured' => ['nullable', 'boolean'],
            'is_trending' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'offer_ends_at' => ['nullable', 'date'],
            'specifications' => ['nullable', 'array'],
            'specifications.*.key' => ['nullable', 'string', 'max:255'],
            'specifications.*.value' => ['nullable', 'string', 'max:1000'],
            'confirm_variant_retirement' => ['nullable', 'boolean'],

            'product_options' => ['nullable', 'array'],
            'product_options.*.id' => ['nullable', 'integer', Rule::exists('product_options', 'id')],
            'product_options.*.name' => ['required', 'string', 'max:255'],
            'product_options.*.slug' => ['required', 'string', 'max:255'],
            'product_options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'product_options.*.is_active' => ['nullable', 'boolean'],
            'product_options.*.values' => ['nullable', 'array'],
            'product_options.*.values.*.id' => ['nullable', 'integer', Rule::exists('product_option_values', 'id')],
            'product_options.*.values.*.name' => ['required', 'string', 'max:255'],
            'product_options.*.values.*.display_name' => ['nullable', 'string', 'max:255'],
            'product_options.*.values.*.hex_value' => ['nullable', 'regex:/^#?[A-Fa-f0-9]{6}$/'],
            'product_options.*.values.*.swatch_image' => ['nullable', 'string', 'max:255'],
            'product_options.*.values.*.swatch_upload' => ['nullable', 'image', 'max:5120'],
            'product_options.*.values.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'product_options.*.values.*.is_active' => ['nullable', 'boolean'],

            'product_images' => ['nullable', 'array'],
            'product_images.*.id' => ['nullable', 'integer', Rule::exists('product_images', 'id')],
            'product_images.*.image_path' => ['nullable', 'string', 'max:255'],
            'product_images.*.upload' => ['nullable', 'image', 'max:5120'],
            'product_images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'product_images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'product_images.*.is_primary' => ['nullable', 'boolean'],

            'color_images' => ['nullable', 'array'],
            'color_images.*.option_value_id' => ['nullable', 'integer', Rule::exists('product_option_values', 'id')],
            'color_images.*.option_value_name' => ['nullable', 'string', 'max:255'],
            'color_images.*.images' => ['nullable', 'array'],
            'color_images.*.images.*.id' => ['nullable', 'integer', Rule::exists('product_images', 'id')],
            'color_images.*.images.*.image_path' => ['nullable', 'string', 'max:255'],
            'color_images.*.images.*.upload' => ['nullable', 'image', 'max:5120'],
            'color_images.*.images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'color_images.*.images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'color_images.*.images.*.is_primary' => ['nullable', 'boolean'],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'variants.*.client_key' => ['nullable', 'string', 'max:255'],
            'variants.*.sku' => ['required', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.option_value_ids' => ['nullable', 'array'],
            'variants.*.option_value_ids.*' => ['nullable', 'integer', Rule::exists('product_option_values', 'id')],
            'variants.*.option_values' => ['nullable', 'array'],
            'variants.*.option_values.*.id' => ['nullable', 'integer', Rule::exists('product_option_values', 'id')],
            'variants.*.option_values.*.option_slug' => ['required_with:variants.*.option_values', 'string', 'max:255'],
            'variants.*.option_values.*.name' => ['required_with:variants.*.option_values', 'string', 'max:255'],
            'variants.*.images' => ['nullable', 'array'],
            'variants.*.images.*.id' => ['nullable', 'integer', Rule::exists('product_images', 'id')],
            'variants.*.images.*.image_path' => ['nullable', 'string', 'max:255'],
            'variants.*.images.*.upload' => ['nullable', 'image', 'max:5120'],
            'variants.*.images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'variants.*.images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'variants.*.images.*.is_primary' => ['nullable', 'boolean'],

            'installment_plans' => ['nullable', 'array'],
            'installment_plans.*.id' => ['nullable', 'integer', Rule::exists('installment_plans', 'id')],
            'installment_plans.*.scope' => ['required', Rule::in(['product', 'variant'])],
            'installment_plans.*.variant_key' => ['nullable', 'string', 'max:255'],
            'installment_plans.*.product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'installment_plans.*.months' => ['required', 'integer', Rule::in([3, 6, 9])],
            'installment_plans.*.total_amount' => ['nullable', 'numeric', 'gt:0'],
            'installment_plans.*.interval_type' => ['nullable', Rule::in(app(InstallmentCalculatorService::class)->intervalTypes())],
            'installment_plans.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        // Validate the same normalized slug that will be persisted. Without this,
        // an empty slug is generated later by the model and can bypass the unique rule.
        $payload['slug'] = Str::slug((string) (filled($payload['slug'] ?? null)
            ? $payload['slug']
            : ($payload['name'] ?? '')));

        if (($payload['specifications'] ?? null) !== null && $this->specificationsUseLegacyKeyValueShape($payload['specifications'])) {
            $payload['specifications'] = collect($payload['specifications'])
                ->map(fn ($value, $key) => [
                    'key' => (string) $key,
                    'value' => is_scalar($value) ? (string) $value : '',
                ])
                ->values()
                ->all();
        }

        foreach (['is_featured', 'is_trending', 'confirm_variant_retirement'] as $field) {
            $payload[$field] = $this->boolean($field);
        }

        foreach (($payload['product_options'] ?? []) as $optionIndex => $option) {
            $payload['product_options'][$optionIndex]['is_active'] = filter_var($option['is_active'] ?? true, FILTER_VALIDATE_BOOL);

            foreach (($option['values'] ?? []) as $valueIndex => $value) {
                $payload['product_options'][$optionIndex]['values'][$valueIndex]['is_active'] = filter_var($value['is_active'] ?? true, FILTER_VALIDATE_BOOL);

                if (filled($value['hex_value'] ?? null) && ! Str::startsWith($value['hex_value'], '#')) {
                    $payload['product_options'][$optionIndex]['values'][$valueIndex]['hex_value'] = '#'.$value['hex_value'];
                }
            }
        }

        foreach (($payload['product_images'] ?? []) as $imageIndex => $image) {
            $payload['product_images'][$imageIndex]['is_primary'] = filter_var($image['is_primary'] ?? false, FILTER_VALIDATE_BOOL);
        }

        foreach (($payload['color_images'] ?? []) as $galleryIndex => $gallery) {
            foreach (($gallery['images'] ?? []) as $imageIndex => $image) {
                $payload['color_images'][$galleryIndex]['images'][$imageIndex]['is_primary'] = filter_var($image['is_primary'] ?? false, FILTER_VALIDATE_BOOL);
            }
        }

        foreach (($payload['variants'] ?? []) as $variantIndex => $variant) {
            $payload['variants'][$variantIndex]['is_active'] = filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOL);

            foreach (($variant['images'] ?? []) as $imageIndex => $image) {
                $payload['variants'][$variantIndex]['images'][$imageIndex]['is_primary'] = filter_var($image['is_primary'] ?? false, FILTER_VALIDATE_BOOL);
            }
        }

        foreach (($payload['installment_plans'] ?? []) as $planIndex => $plan) {
            $payload['installment_plans'][$planIndex]['is_active'] = filter_var($plan['is_active'] ?? true, FILTER_VALIDATE_BOOL);
            $payload['installment_plans'][$planIndex]['scope'] = ($plan['scope'] ?? 'variant') === 'product' ? 'product' : 'variant';
            $payload['installment_plans'][$planIndex]['interval_type'] = InstallmentCalculatorService::INTERVAL_MONTHLY;
            $payload['installment_plans'][$planIndex]['down_payment'] = 0;
            $payload['installment_plans'][$planIndex]['financing_fee'] = 0;

            // Backward compatibility for this project's original plan editor,
            // which submitted terms and fees without an explicit total.
            if (! filled($plan['total_amount'] ?? null)) {
                $targetKey = (string) ($plan['variant_key'] ?? $plan['product_variant_id'] ?? '');
                $target = collect($payload['variants'] ?? [])->first(function (array $variant) use ($targetKey): bool {
                    return $targetKey !== '' && in_array($targetKey, [
                        (string) ($variant['client_key'] ?? ''),
                        (string) ($variant['id'] ?? ''),
                    ], true);
                });
                $target ??= collect($payload['variants'] ?? [])->first();
                if ($target && filled($target['price'] ?? null)) {
                    $payload['installment_plans'][$planIndex]['total_amount'] = $target['price'];
                }
            }
        }

        foreach (($payload['variants'] ?? []) as $variantIndex => $variant) {
            $variantKey = (string) ($variant['client_key'] ?? $variant['id'] ?? 'variant-'.$variantIndex);
            $threePaymentPlan = collect($payload['installment_plans'] ?? [])->first(fn (array $plan) => (string) ($plan['variant_key'] ?? $plan['product_variant_id'] ?? '') === $variantKey && (int) ($plan['months'] ?? 0) === 3);

            if ($threePaymentPlan !== null && filled($threePaymentPlan['total_amount'] ?? null)) {
                $payload['variants'][$variantIndex]['price'] = $threePaymentPlan['total_amount'];
            }
        }

        $this->replace($payload);
    }

    protected function specificationsUseLegacyKeyValueShape(mixed $specifications): bool
    {
        if (! is_array($specifications) || array_is_list($specifications)) {
            return false;
        }

        return collect($specifications)->every(fn ($value, $key) => is_string($key) && ! is_array($value));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateSpecifications($validator);
            $this->validateOptionValues($validator);
            $this->validateImages($validator);
            $this->validateOwnedRecords($validator);
            $this->validateVariants($validator);
            $this->validateInstallmentPlans($validator);

            if ($validator->errors()->isNotEmpty()) {
                $this->replace($this->preserveUploadedImagesForRetry($this->all()));
            }
        });
    }

    /** Store selected images temporarily so a validation redirect can restore their previews. */
    protected function preserveUploadedImagesForRetry(array $payload): array
    {
        $draftId = $this->session()->get('product_form_draft_id') ?? (string) Str::uuid();
        $this->session()->put('product_form_draft_id', $draftId);
        $store = fn (mixed $file): ?string => $file instanceof UploadedFile && $file->isValid()
            ? $file->store("product-drafts/{$draftId}", 'public')
            : null;

        foreach ($this->file('product_images', []) as $imageIndex => $image) {
            if ($path = $store($image['upload'] ?? null)) {
                $payload['product_images'][$imageIndex]['image_path'] = $path;
            }
        }

        foreach ($this->file('color_images', []) as $galleryIndex => $gallery) {
            foreach ($gallery['images'] ?? [] as $imageIndex => $image) {
                if ($path = $store($image['upload'] ?? null)) {
                    $payload['color_images'][$galleryIndex]['images'][$imageIndex]['image_path'] = $path;
                }
            }
        }

        foreach ($this->file('variants', []) as $variantIndex => $variant) {
            foreach ($variant['images'] ?? [] as $imageIndex => $image) {
                if ($path = $store($image['upload'] ?? null)) {
                    $payload['variants'][$variantIndex]['images'][$imageIndex]['image_path'] = $path;
                }
            }
        }

        return $payload;
    }

    protected function validateSpecifications(Validator $validator): void
    {
        $specifications = collect($this->input('specifications', []))
            ->filter(function ($specification) {
                return is_array($specification)
                    && (filled($specification['key'] ?? null) || filled($specification['value'] ?? null));
            });

        foreach ($specifications as $index => $specification) {
            if (blank($specification['key'] ?? null) || blank($specification['value'] ?? null)) {
                $validator->errors()->add("specifications.{$index}.key", 'Each specification requires both a key and a value.');
            }
        }
    }

    protected function validateOptionValues(Validator $validator): void
    {
        $submittedSlugs = [];

        foreach ($this->input('product_options', []) as $optionIndex => $option) {
            $slug = Str::lower(trim((string) ($option['slug'] ?? '')));

            if ($slug === '') {
                continue;
            }

            if (in_array($slug, $submittedSlugs, true)) {
                $validator->errors()->add("product_options.{$optionIndex}.slug", 'Each product option slug must be unique.');
            }

            $submittedSlugs[] = $slug;
            $names = [];

            foreach ($option['values'] ?? [] as $valueIndex => $value) {
                $normalized = Str::lower(trim((string) ($value['name'] ?? '')));

                if ($normalized === '') {
                    continue;
                }

                if (in_array($normalized, $names, true)) {
                    $validator->errors()->add("product_options.{$optionIndex}.values.{$valueIndex}.name", 'Duplicate option values are not allowed.');
                }

                $names[] = $normalized;
            }
        }
    }

    protected function validateImages(Validator $validator): void
    {
        foreach ($this->input('product_images', []) as $index => $image) {
            if (! $this->hasImagePayload($image, "product_images.{$index}.upload")) {
                $validator->errors()->add("product_images.{$index}.upload", 'Each product image needs an uploaded file or an existing saved image.');
            }
        }

        foreach ($this->input('color_images', []) as $galleryIndex => $gallery) {
            foreach ($gallery['images'] ?? [] as $imageIndex => $image) {
                if (! $this->hasImagePayload($image, "color_images.{$galleryIndex}.images.{$imageIndex}.upload")) {
                    $validator->errors()->add("color_images.{$galleryIndex}.images.{$imageIndex}.upload", 'Each color image needs an uploaded file or an existing saved image.');
                }
            }
        }

        foreach ($this->input('variants', []) as $variantIndex => $variant) {
            foreach ($variant['images'] ?? [] as $imageIndex => $image) {
                if (! $this->hasImagePayload($image, "variants.{$variantIndex}.images.{$imageIndex}.upload")) {
                    $validator->errors()->add("variants.{$variantIndex}.images.{$imageIndex}.upload", 'Each variant image needs an uploaded file or an existing saved image.');
                }
            }
        }
    }

    protected function validateVariants(Validator $validator): void
    {
        $optionLookup = $this->submittedOptionLookup();
        $requiredOptionSlugs = collect($this->input('product_options', []))
            ->filter(fn (array $option) => filter_var($option['is_active'] ?? true, FILTER_VALIDATE_BOOL))
            ->filter(fn (array $option) => collect($option['values'] ?? [])->contains(
                fn (array $value) => filled($value['name'] ?? null) && filter_var($value['is_active'] ?? true, FILTER_VALIDATE_BOOL)
            ))
            ->map(fn (array $option) => Str::lower(trim((string) ($option['slug'] ?? ''))))
            ->filter()
            ->unique()
            ->values();
        $skus = [];
        $barcodes = [];
        $signatures = [];

        foreach ($this->input('variants', []) as $variantIndex => $variant) {
            $sku = Str::upper(trim((string) ($variant['sku'] ?? '')));

            if ($sku !== '') {
                if (in_array($sku, $skus, true)) {
                    $validator->errors()->add("variants.{$variantIndex}.sku", 'Variant SKUs must be unique.');
                }

                $skus[] = $sku;
            }

            $barcode = trim((string) ($variant['barcode'] ?? ''));
            if ($barcode !== '') {
                if (in_array($barcode, $barcodes, true)) {
                    $validator->errors()->add("variants.{$variantIndex}.barcode", 'Variant barcodes must be unique.');
                }
                $barcodes[] = $barcode;

                $existingBarcode = ProductVariant::query()->where('barcode', $barcode)->first();
                if ($existingBarcode && (int) ($variant['id'] ?? 0) !== $existingBarcode->id) {
                    $validator->errors()->add("variants.{$variantIndex}.barcode", 'This barcode is already assigned to another variant.');
                }
            }

            $resolvedValues = $this->resolveSubmittedVariantValues($variant);

            if ($resolvedValues->isEmpty()) {
                $validator->errors()->add("variants.{$variantIndex}.option_values", 'Each variant must include at least one option value.');
            }

            $variantOptionSlugs = $resolvedValues
                ->pluck('option_slug')
                ->map(fn ($slug) => Str::lower(trim((string) $slug)))
                ->filter()
                ->unique()
                ->values();

            if (
                $requiredOptionSlugs->isNotEmpty()
                && ($variantOptionSlugs->diff($requiredOptionSlugs)->isNotEmpty() || $requiredOptionSlugs->diff($variantOptionSlugs)->isNotEmpty())
            ) {
                $validator->errors()->add(
                    "variants.{$variantIndex}.option_values",
                    'Each variant must include one value for every active option group.'
                );
            }

            $signature = $resolvedValues
                ->map(fn (array $value) => $value['option_slug'].':'.Str::lower($value['name']))
                ->sort()
                ->implode('|');

            if ($signature !== '' && in_array($signature, $signatures, true)) {
                $validator->errors()->add("variants.{$variantIndex}.option_values", 'Duplicate option combinations are not allowed.');
            }

            if ($signature !== '') {
                $signatures[] = $signature;
            }

            foreach ($resolvedValues as $valueIndex => $value) {
                $knownValues = $optionLookup[$value['option_slug']] ?? [];

                if (! in_array(Str::lower($value['name']), $knownValues, true)) {
                    $validator->errors()->add("variants.{$variantIndex}.option_values.{$valueIndex}.name", 'Variant option values must match a submitted product option value.');
                }
            }

            $compareAt = $variant['compare_at_price'] ?? null;
            $price = $variant['price'] ?? null;

            if ($compareAt !== null && $compareAt !== '' && $price !== null && $price !== '' && (float) $compareAt < (float) $price) {
                $validator->errors()->add("variants.{$variantIndex}.compare_at_price", 'Compare-at price must be greater than or equal to the selling price.');
            }
        }
    }

    protected function validateInstallmentPlans(Validator $validator): void
    {
        $product = $this->route('product');
        $submittedVariants = collect($this->input('variants', []))
            ->mapWithKeys(function (array $variant, int $index) {
                $clientKey = (string) ($variant['client_key'] ?? $variant['id'] ?? 'variant-'.$index);

                return [$clientKey => $variant];
            });

        $activeScopes = [];

        foreach ($this->input('installment_plans', []) as $planIndex => $plan) {
            $scope = ($plan['scope'] ?? 'product') === 'variant' ? 'variant' : 'product';
            $payments = (int) ($plan['months'] ?? $plan['number_of_payments'] ?? 0);
            $variantId = filled($plan['product_variant_id'] ?? null) ? (int) $plan['product_variant_id'] : null;
            $variantKey = filled($plan['variant_key'] ?? null) ? (string) $plan['variant_key'] : null;

            if ($scope === 'variant' && $variantId === null && $variantKey === null) {
                $validator->errors()->add("installment_plans.{$planIndex}.variant_key", 'Variant-specific plans must target a variant.');

                continue;
            }

            if ($scope === 'variant' && $variantId !== null && $product !== null) {
                $variant = ProductVariant::query()->find($variantId);

                if (! $variant || $variant->product_id !== $product->id) {
                    $validator->errors()->add("installment_plans.{$planIndex}.product_variant_id", 'Plans can only target variants that belong to this product.');
                }
            }

            if ($scope === 'variant' && $variantId !== null && $product === null) {
                $submittedVariantIds = $submittedVariants
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if (! in_array($variantId, $submittedVariantIds, true)) {
                    $validator->errors()->add("installment_plans.{$planIndex}.product_variant_id", 'Plans can only target variants that belong to this product.');
                }
            }

            if ($scope === 'variant' && $variantKey !== null && ! $submittedVariants->has($variantKey) && $variantId === null) {
                $validator->errors()->add("installment_plans.{$planIndex}.variant_key", 'The selected variant is not part of this product submission.');
            }

            if (filter_var($plan['is_active'] ?? true, FILTER_VALIDATE_BOOL)) {
                $scopeKey = $scope === 'variant'
                    ? 'variant:'.($variantId ?? $variantKey)
                    : 'product';
                $signature = $payments.'|'.($plan['interval_type'] ?? InstallmentCalculatorService::INTERVAL_MONTHLY).'|'.$scopeKey;

                if (in_array($signature, $activeScopes, true)) {
                    $validator->errors()->add("installment_plans.{$planIndex}.months", 'Duplicate active plans with the same payment count, interval, and scope are not allowed.');
                }

                $activeScopes[] = $signature;
            }

            if ($planId = $plan['id'] ?? null) {
                $existingPlan = InstallmentPlan::query()->find($planId);

                if ($existingPlan && $product !== null && $existingPlan->product_id !== $product->id) {
                    $validator->errors()->add("installment_plans.{$planIndex}.id", 'The selected installment plan does not belong to this product.');
                }
            }
        }

        $requiresCompleteVariantPlans = ($this->input('status') === ProductStatus::Active->value)
            && collect($this->input('installment_plans', []))->isNotEmpty()
            && collect($this->input('installment_plans', []))->every(fn (array $plan) => ($plan['scope'] ?? 'variant') === 'variant');
        $activeVariants = $requiresCompleteVariantPlans
            ? $submittedVariants->filter(fn (array $variant) => filter_var($variant['is_active'] ?? true, FILTER_VALIDATE_BOOL))
            : collect();
        foreach ($activeVariants as $variantKey => $variant) {
            foreach ([3, 6, 9] as $payments) {
                $plan = collect($this->input('installment_plans', []))->first(function (array $entry) use ($payments, $variantKey, $variant): bool {
                    $matchesVariant = (string) ($entry['variant_key'] ?? '') === (string) $variantKey
                        || (filled($entry['product_variant_id'] ?? null) && (int) $entry['product_variant_id'] === (int) ($variant['id'] ?? 0));

                    return (int) ($entry['months'] ?? 0) === $payments && $matchesVariant;
                });

                if ($plan === null) {
                    $validator->errors()->add('installment_plans', "{$payments}-payment total is required for every active variant.");
                }
            }
        }
    }

    protected function validateOwnedRecords(Validator $validator): void
    {
        $product = $this->route('product');

        if (! $product) {
            return;
        }

        $optionIds = $product->productOptions()->pluck('id')->all();
        $valueIds = ProductOptionValue::query()
            ->select('product_option_values.id')
            ->whereHas('productOption', fn ($query) => $query->where('product_id', $product->id))
            ->pluck('product_option_values.id')
            ->all();
        $variantIds = $product->variants()->pluck('id')->all();
        $imageIds = $product->images()->pluck('id')->merge(
            $product->variants()->with('images:id,product_variant_id')->get()->flatMap->images->pluck('id')
        )->all();
        $planIds = $product->installmentPlans()->pluck('id')->all();

        foreach ($this->input('product_options', []) as $index => $option) {
            if (filled($option['id'] ?? null) && ! in_array((int) $option['id'], $optionIds, true)) {
                $validator->errors()->add("product_options.{$index}.id", 'The selected option does not belong to this product.');
            }

            foreach ($option['values'] ?? [] as $valueIndex => $value) {
                if (filled($value['id'] ?? null) && ! in_array((int) $value['id'], $valueIds, true)) {
                    $validator->errors()->add("product_options.{$index}.values.{$valueIndex}.id", 'The selected option value does not belong to this product.');
                }
            }
        }

        foreach ($this->input('variants', []) as $variantIndex => $variant) {
            if (filled($variant['id'] ?? null) && ! in_array((int) $variant['id'], $variantIds, true)) {
                $validator->errors()->add("variants.{$variantIndex}.id", 'The selected variant does not belong to this product.');
            }

            foreach ($variant['images'] ?? [] as $imageIndex => $image) {
                if (filled($image['id'] ?? null) && ! in_array((int) $image['id'], $imageIds, true)) {
                    $validator->errors()->add("variants.{$variantIndex}.images.{$imageIndex}.id", 'The selected image does not belong to this product.');
                }
            }
        }

        foreach ($this->input('product_images', []) as $imageIndex => $image) {
            if (filled($image['id'] ?? null) && ! in_array((int) $image['id'], $imageIds, true)) {
                $validator->errors()->add("product_images.{$imageIndex}.id", 'The selected image does not belong to this product.');
            }
        }

        foreach ($this->input('color_images', []) as $galleryIndex => $gallery) {
            $colorId = filled($gallery['option_value_id'] ?? null) ? (int) $gallery['option_value_id'] : null;
            if ($colorId !== null && ! in_array($colorId, $valueIds, true)) {
                $validator->errors()->add("color_images.{$galleryIndex}.option_value_id", 'The selected color does not belong to this product.');
            }

            foreach ($gallery['images'] ?? [] as $imageIndex => $image) {
                if (filled($image['id'] ?? null) && ! in_array((int) $image['id'], $imageIds, true)) {
                    $validator->errors()->add("color_images.{$galleryIndex}.images.{$imageIndex}.id", 'The selected image does not belong to this product.');
                }
            }
        }

        foreach ($this->input('installment_plans', []) as $planIndex => $plan) {
            if (filled($plan['id'] ?? null) && ! in_array((int) $plan['id'], $planIds, true)) {
                $validator->errors()->add("installment_plans.{$planIndex}.id", 'The selected installment plan does not belong to this product.');
            }
        }
    }

    protected function hasImagePayload(array $image, string $uploadField): bool
    {
        return filled($image['image_path'] ?? null) || $this->hasFile($uploadField);
    }

    protected function submittedOptionLookup(): array
    {
        return collect($this->input('product_options', []))
            ->mapWithKeys(function (array $option) {
                $slug = Str::lower(trim((string) ($option['slug'] ?? '')));

                return $slug === ''
                    ? []
                    : [$slug => collect($option['values'] ?? [])
                        ->pluck('name')
                        ->filter()
                        ->map(fn ($name) => Str::lower(trim((string) $name)))
                        ->unique()
                        ->values()
                        ->all()];
            })
            ->all();
    }

    protected function resolveSubmittedVariantValues(array $variant): Collection
    {
        $fromRows = collect($variant['option_values'] ?? [])
            ->filter(fn (array $value) => filled($value['option_slug'] ?? null) && filled($value['name'] ?? null))
            ->map(fn (array $value) => [
                'option_slug' => Str::lower(trim((string) $value['option_slug'])),
                'name' => trim((string) $value['name']),
            ]);

        if ($fromRows->isNotEmpty()) {
            return $fromRows->values();
        }

        $ids = collect($variant['option_value_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ProductOptionValue::query()
            ->with('productOption')
            ->whereKey($ids->all())
            ->get()
            ->map(fn (ProductOptionValue $value) => [
                'option_slug' => Str::lower($value->productOption?->slug ?? ''),
                'name' => $value->name,
            ])
            ->filter(fn (array $value) => $value['option_slug'] !== '' && $value['name'] !== '')
            ->values();
    }
}
