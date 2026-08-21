<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductCatalogService
{
    protected array $variantRetirementWarnings = [];

    public function __construct(
        protected InstallmentPlanService $installmentPlanService,
    ) {}

    public function save(Product $product, array $payload): Product
    {
        $this->variantRetirementWarnings = [];
        $payload = $this->normalizePayload($payload);

        return DB::transaction(function () use ($product, $payload) {
            $product->fill($payload);
            $product->specifications = $payload['specifications'] ?? [];
            $product->save();

            $optionValues = $this->syncOptions($product, $payload['product_options'] ?? []);
            $this->syncImages($product, $payload['product_images'] ?? []);
            $this->syncColorImages($product, $payload['color_images'] ?? [], $optionValues);
            $variantMap = $this->syncVariants($product, $payload['variants'] ?? [], $optionValues);
            $this->syncInstallmentPlans($product, $payload['installment_plans'] ?? [], $variantMap);

            return $product->fresh([
                'category',
                'productOptions.values',
                'installmentPlans',
                'images.optionValue',
                'variants.images',
                'variants.optionValues.productOption',
            ]);
        });
    }

    public function variantRetirementWarnings(): array
    {
        return $this->variantRetirementWarnings;
    }

    /** Permanently remove a catalog product, its dependent records, and uploaded assets. */
    public function delete(Product $product): void
    {
        $assetPaths = DB::transaction(function () use ($product): array {
            $productId = $product->id;
            $imagePaths = ProductImage::query()
                ->where('product_id', $productId)
                ->pluck('image_path');
            $swatchPaths = ProductOptionValue::query()
                ->whereHas('productOption', fn ($query) => $query->where('product_id', $productId))
                ->pluck('swatch_image');

            $product->forceDelete();

            return $imagePaths
                ->merge($swatchPaths)
                ->filter(fn ($path) => filled($path))
                ->unique()
                ->values()
                ->all();
        });

        Storage::disk('public')->delete($assetPaths);
    }

    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $product->load([
                'productOptions.values',
                'images.optionValue',
                'variants.images',
                'variants.optionValues.productOption',
                'installmentPlans',
            ]);

            $copy = $product->replicate([
                'slug',
                'published_at',
            ]);
            $copy->name = $product->name.' Copy';
            $copy->slug = Str::slug($copy->name.'-'.Str::random(6));
            $copy->status = ProductStatus::Draft;
            $copy->published_at = null;
            $copy->save();

            $optionIdMap = [];
            $valueIdMap = [];

            foreach ($product->productOptions as $option) {
                $newOption = $option->replicate();
                $newOption->product()->associate($copy);
                $newOption->save();
                $optionIdMap[$option->id] = $newOption->id;

                foreach ($option->values as $value) {
                    $newValue = $value->replicate();
                    $newValue->product_option_id = $newOption->id;
                    $newValue->save();
                    $valueIdMap[$value->id] = $newValue->id;
                }
            }

            foreach ($product->images as $image) {
                $newImage = $image->replicate();
                $newImage->product_id = $copy->id;
                $newImage->product_variant_id = null;
                $newImage->product_option_value_id = $image->product_option_value_id ? ($valueIdMap[$image->product_option_value_id] ?? null) : null;
                $newImage->save();
            }

            $variantIdMap = [];

            foreach ($product->variants as $variant) {
                $newVariant = $variant->replicate([
                    'sku',
                    'option_signature',
                ]);
                $newVariant->product_id = $copy->id;
                $newVariant->sku = $this->uniqueDuplicateSku($variant->sku);
                $newVariant->save();

                $newOptionValueIds = $variant->optionValues
                    ->pluck('id')
                    ->map(fn ($id) => $valueIdMap[$id] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                $newVariant->syncOptionValues($newOptionValueIds);
                $variantIdMap[$variant->id] = $newVariant->id;

                foreach ($variant->images as $image) {
                    $newImage = $image->replicate();
                    $newImage->product_id = $copy->id;
                    $newImage->product_variant_id = $newVariant->id;
                    $newImage->save();
                }
            }

            foreach ($product->installmentPlans as $plan) {
                $newPlan = $plan->replicate();
                $newPlan->product_id = $copy->id;
                $newPlan->product_variant_id = $plan->product_variant_id ? ($variantIdMap[$plan->product_variant_id] ?? null) : null;
                $newPlan->save();
            }

            return $copy->fresh([
                'category',
                'productOptions.values',
                'images.optionValue',
                'variants.images',
                'variants.optionValues.productOption',
                'installmentPlans',
            ]);
        });
    }

    protected function normalizePayload(array $payload): array
    {
        $payload['slug'] = filled($payload['slug'] ?? null) ? Str::slug((string) $payload['slug']) : null;
        $specifications = $payload['specifications'] ?? [];

        if (is_array($specifications) && ! array_is_list($specifications)) {
            $specifications = collect($specifications)
                ->map(fn ($value, $key) => [
                    'key' => (string) $key,
                    'value' => is_scalar($value) ? (string) $value : '',
                ])
                ->values()
                ->all();
        }

        $payload['specifications'] = collect($specifications)
            ->filter(fn (array $specification) => filled($specification['key'] ?? null) && filled($specification['value'] ?? null))
            ->map(fn (array $specification) => [
                'key' => trim((string) $specification['key']),
                'value' => trim((string) $specification['value']),
            ])
            ->values()
            ->all();

        $payload['product_options'] = collect($payload['product_options'] ?? [])
            ->map(function (array $option, int $index) {
                return [
                    'id' => $option['id'] ?? null,
                    'name' => trim((string) $option['name']),
                    'slug' => Str::slug((string) ($option['slug'] ?? $option['name'])),
                    'sort_order' => $option['sort_order'] ?? $index,
                    'is_active' => (bool) ($option['is_active'] ?? true),
                    'values' => collect($option['values'] ?? [])
                        ->filter(fn (array $value) => filled($value['name'] ?? null))
                        ->map(function (array $value, int $valueIndex) {
                            return [
                                'id' => $value['id'] ?? null,
                                'name' => trim((string) $value['name']),
                                'display_name' => trim((string) ($value['display_name'] ?? $value['name'] ?? '')),
                                'hex_value' => $value['hex_value'] ?? null,
                                'swatch_image' => $value['swatch_image'] ?? null,
                                'swatch_upload' => $value['swatch_upload'] ?? null,
                                'sort_order' => $value['sort_order'] ?? $valueIndex,
                                'is_active' => (bool) ($value['is_active'] ?? true),
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $payload['product_images'] = $this->normalizeImages($payload['product_images'] ?? []);
        $payload['color_images'] = collect($payload['color_images'] ?? [])
            ->map(fn (array $gallery) => [
                'option_value_id' => filled($gallery['option_value_id'] ?? null) ? (int) $gallery['option_value_id'] : null,
                // A new color has no ID until this transaction saves its option value.
                'option_value_name' => trim((string) ($gallery['option_value_name'] ?? '')),
                'images' => $this->normalizeImages($gallery['images'] ?? []),
            ])->values()->all();
        $payload['variants'] = collect($payload['variants'] ?? [])
            ->map(function (array $variant, int $index) {
                return [
                    'id' => $variant['id'] ?? null,
                    'client_key' => (string) ($variant['client_key'] ?? $variant['id'] ?? 'variant-'.$index),
                    'sku' => trim((string) $variant['sku']),
                    'price' => round((float) $variant['price'], 2),
                    'compare_at_price' => filled($variant['compare_at_price'] ?? null) ? round((float) $variant['compare_at_price'], 2) : null,
                    'stock_quantity' => (int) $variant['stock_quantity'],
                    'is_active' => (bool) ($variant['is_active'] ?? true),
                    'option_value_ids' => collect($variant['option_value_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->values()->all(),
                    'option_values' => collect($variant['option_values'] ?? [])
                        ->filter(fn (array $value) => filled($value['option_slug'] ?? null) && filled($value['name'] ?? null))
                        ->map(fn (array $value) => [
                            'id' => $value['id'] ?? null,
                            'option_slug' => Str::slug((string) $value['option_slug']),
                            'name' => trim((string) $value['name']),
                        ])
                        ->values()
                        ->all(),
                    'images' => $this->normalizeImages($variant['images'] ?? []),
                ];
            })
            ->values()
            ->all();

        $payload['installment_plans'] = collect($payload['installment_plans'] ?? [])
            ->map(fn (array $plan, int $index) => [
                'id' => $plan['id'] ?? null,
                'scope' => ($plan['scope'] ?? 'product') === 'variant' ? 'variant' : 'product',
                'variant_key' => filled($plan['variant_key'] ?? null) ? (string) $plan['variant_key'] : null,
                'product_variant_id' => filled($plan['product_variant_id'] ?? null) ? (int) $plan['product_variant_id'] : null,
                'number_of_payments' => (int) ($plan['number_of_payments'] ?? $plan['months'] ?? 0),
                'total_amount' => round((float) ($plan['total_amount'] ?? 0), 2),
                'down_payment' => round((float) ($plan['down_payment'] ?? 0), 2),
                'financing_fee' => round((float) ($plan['financing_fee'] ?? 0), 2),
                'interval_type' => $plan['interval_type'] ?? InstallmentCalculatorService::INTERVAL_MONTHLY,
                'is_active' => (bool) ($plan['is_active'] ?? true),
                'sort_order' => $plan['sort_order'] ?? $index,
            ])
            ->values()
            ->all();

        return $payload;
    }

    protected function normalizeImages(array $rows): array
    {
        return collect($rows)
            ->map(function (array $image, int $index) {
                return [
                    'id' => $image['id'] ?? null,
                    'image_path' => $image['image_path'] ?? null,
                    'upload' => $image['upload'] ?? null,
                    'alt_text' => $image['alt_text'] ?? null,
                    'sort_order' => $image['sort_order'] ?? $index,
                    'is_primary' => (bool) ($image['is_primary'] ?? false),
                ];
            })
            ->filter(fn (array $image) => filled($image['image_path']) || $image['upload'] instanceof UploadedFile)
            ->values()
            ->all();
    }

    protected function syncOptions(Product $product, array $rows): Collection
    {
        $existingOptions = $product->productOptions()->with('values')->get()->keyBy('id');
        $savedValues = collect();
        $keptOptionIds = [];

        foreach ($rows as $index => $row) {
            $option = isset($row['id']) ? $existingOptions->get((int) $row['id']) : new ProductOption;
            $option ??= new ProductOption;
            $option->product()->associate($product);
            $option->fill([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'sort_order' => $row['sort_order'] ?? $index,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
            $option->save();
            $keptOptionIds[] = $option->id;

            $savedValues = $savedValues->merge($this->syncOptionValues($option, $row['values'] ?? []));
        }

        $product->productOptions()
            ->whereNotIn('id', $keptOptionIds ?: [0])
            ->update(['is_active' => false]);

        return $savedValues->keyBy('id');
    }

    protected function syncOptionValues(ProductOption $option, array $rows): Collection
    {
        $existing = $option->values()->get()->keyBy('id');
        $savedValues = collect();
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $value = isset($row['id']) ? $existing->get((int) $row['id']) : new ProductOptionValue;
            $value ??= new ProductOptionValue;
            $previousSwatchPath = $value->swatch_image;
            $value->productOption()->associate($option);
            $value->fill([
                'name' => $row['name'],
                'slug' => Str::slug($row['name']),
                'display_name' => $row['display_name'] ?: $row['name'],
                'hex_value' => $row['hex_value'] ?? null,
                'swatch_image' => $this->storeOptionSwatch($row['swatch_upload'] ?? null, $row['swatch_image'] ?? null, $option->product_id),
                'sort_order' => $row['sort_order'] ?? $index,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
            $value->save();
            if ($previousSwatchPath !== $value->swatch_image) {
                $this->deletePublicPathIfUnused($previousSwatchPath, ProductOptionValue::class, 'swatch_image', $value->id);
            }
            $keptIds[] = $value->id;
            $savedValues->push($value->fresh('productOption'));
        }

        $option->values()
            ->whereNotIn('id', $keptIds ?: [0])
            ->get()
            ->each(function (ProductOptionValue $value): void {
                $this->deletePublicPathIfUnused($value->swatch_image, ProductOptionValue::class, 'swatch_image', $value->id);
                $value->update([
                    'is_active' => false,
                    'swatch_image' => null,
                ]);
            });

        return $savedValues;
    }

    protected function syncInstallmentPlans(Product $product, array $rows, Collection $variantMap): void
    {
        $existing = $product->installmentPlans()->get()->keyBy('id');
        $keptIds = [];

        foreach ($rows as $row) {
            $plan = isset($row['id']) ? $existing->get((int) $row['id']) : new InstallmentPlan;
            $plan ??= new InstallmentPlan;
            $plan->product()->associate($product);

            $variant = null;

            if (($row['scope'] ?? 'product') === 'variant') {
                $variant = $variantMap->get($row['variant_key'] ?? $row['product_variant_id']);
            }

            $price = (float) ($row['total_amount'] ?? 0);

            $resolved = $this->installmentPlanService->resolvePlanPayload(
                $price,
                (int) $row['number_of_payments'],
                0,
                0,
                (string) ($row['interval_type'] ?? InstallmentCalculatorService::INTERVAL_MONTHLY),
            );

            $plan->fill([
                'product_variant_id' => $variant?->id,
                'variant_key' => $row['scope'] === 'variant' ? ($row['variant_key'] ?? (string) $variant?->id) : null,
                'number_of_payments' => (int) $row['number_of_payments'],
                'down_payment' => $resolved['down_payment'],
                'financing_fee' => $resolved['financing_fee'],
                'installment_amount' => $resolved['installment_amount'],
                'total_amount' => $price,
                'interval_type' => $row['interval_type'] ?? InstallmentCalculatorService::INTERVAL_MONTHLY,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);
            $plan->save();
            $keptIds[] = $plan->id;
        }

        $product->installmentPlans()
            ->whereNotIn('id', $keptIds ?: [0])
            ->delete();
    }

    protected function syncVariants(Product $product, array $rows, Collection $optionValues): Collection
    {
        $existingVariants = $product->variants()->with(['images', 'optionValues.productOption'])->get();
        $existing = $existingVariants->keyBy('id');
        $existingBySku = $existingVariants->keyBy(fn (ProductVariant $variant) => Str::upper(trim($variant->sku)));

        if ($rows === []) {
            return $existingVariants->reduce(function (Collection $variants, ProductVariant $variant): Collection {
                $variants->put((string) $variant->id, $variant);

                return $variants;
            }, collect());
        }
        $keptIds = [];
        $saved = collect();
        $submittedVariantIds = collect($rows)
            ->map(function (array $row) use ($existingBySku) {
                $submittedId = $row['id'] ?? $row['client_key'] ?? null;
                if (filled($submittedId) && ctype_digit((string) $submittedId)) {
                    return (int) $submittedId;
                }

                return $existingBySku->get(Str::upper(trim((string) ($row['sku'] ?? ''))))?->id;
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();
        $submittedSkus = collect($rows)
            ->pluck('sku')
            ->filter()
            ->map(fn ($sku) => Str::upper(trim((string) $sku)))
            ->all();
        $retired = $product->variants()
            ->whereNotIn('id', $submittedVariantIds ?: [0])
            ->get();

        foreach ($retired as $variant) {
            if (in_array(Str::upper($variant->sku), $submittedSkus, true)) {
                $variant->forceFill([
                    'sku' => $this->uniqueRetiredSku($variant->sku),
                ])->save();
            }
        }

        foreach ($rows as $row) {
            $selectedValueIds = $this->resolveOptionValueIds($optionValues, $row);
            $submittedId = $row['id'] ?? $row['client_key'] ?? null;
            $variant = filled($submittedId) && ctype_digit((string) $submittedId)
                ? $existing->get((int) $submittedId)
                : $existingBySku->get(Str::upper(trim((string) ($row['sku'] ?? ''))));
            $variant ??= new ProductVariant;
            $variant->product()->associate($product);
            $variant->fill([
                'sku' => $row['sku'],
                'price' => $row['price'],
                'compare_at_price' => $row['compare_at_price'],
                'stock_quantity' => $row['stock_quantity'],
                'is_active' => (bool) ($row['is_active'] ?? true),
                'option_signature' => ProductVariant::buildOptionSignature($selectedValueIds),
            ]);
            $variant->save();
            $variant->optionValues()->sync($selectedValueIds);
            $this->syncVariantImages($product, $variant, $row['images'] ?? []);
            $keptIds[] = $variant->id;
            $saved->put($row['client_key'], $variant->fresh(['images', 'optionValues.productOption']));
            $saved->put((string) $variant->id, $variant->fresh(['images', 'optionValues.productOption']));
        }

        foreach ($retired as $variant) {
            if ($variant->stock_quantity > 0) {
                $this->variantRetirementWarnings[] = sprintf(
                    '%s kept as inactive because it still has %d unit(s) in stock.',
                    $variant->sku,
                    $variant->stock_quantity,
                );
            }

            $variant->forceFill(['is_active' => false])->save();
        }

        return $saved;
    }

    protected function syncImages(Product $product, array $rows): void
    {
        $existing = $product->generalImages()->get()->keyBy('id');
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $image = isset($row['id']) ? $existing->get((int) $row['id']) : new ProductImage;
            $image ??= new ProductImage;
            $previousPath = $image->image_path;
            $image->product()->associate($product);
            $image->variant()->dissociate();
            $image->optionValue()->dissociate();
            $image->fill([
                'image_path' => $this->storeImageUpload($row['upload'] ?? null, $row['image_path'] ?? null, $product->id),
                'alt_text' => $row['alt_text'] ?? null,
                'sort_order' => $row['sort_order'] ?? $index,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ]);
            $image->save();
            if ($previousPath !== $image->image_path) {
                $this->deletePublicPathIfUnused($previousPath, ProductImage::class, 'image_path', $image->id);
            }
            $keptIds[] = $image->id;
        }

        $this->deleteRemovedImages($product->generalImages()->whereNotIn('id', $keptIds ?: [0])->get());
        $product->generalImages()->whereNotIn('id', $keptIds ?: [0])->delete();
    }

    protected function syncColorImages(Product $product, array $galleries, Collection $optionValues): void
    {
        foreach ($galleries as $gallery) {
            $color = filled($gallery['option_value_id'] ?? null)
                ? $optionValues->get((int) $gallery['option_value_id'])
                : $optionValues->first(fn (ProductOptionValue $value) => $value->productOption?->slug === ProductOption::COLOR_SLUG
                    && Str::lower($value->name) === Str::lower($gallery['option_value_name'] ?? ''));

            if (! $color || $color->productOption?->slug !== ProductOption::COLOR_SLUG) continue;

            $existing = $product->images()->where('product_option_value_id', $color->id)->get()->keyBy('id');
            $keptIds = [];
            foreach ($gallery['images'] as $index => $row) {
                $image = isset($row['id']) ? $existing->get((int) $row['id']) : new ProductImage;
                $image ??= new ProductImage;
                $previousPath = $image->image_path;
                $image->product()->associate($product);
                $image->variant()->dissociate();
                $image->optionValue()->associate($color);
                $image->fill([
                    'image_path' => $this->storeImageUpload($row['upload'] ?? null, $row['image_path'] ?? null, $product->id),
                    'alt_text' => $row['alt_text'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ]);
                $image->save();
                if ($previousPath !== $image->image_path) $this->deletePublicPathIfUnused($previousPath, ProductImage::class, 'image_path', $image->id);
                $keptIds[] = $image->id;
            }

            $removed = $product->images()->where('product_option_value_id', $color->id)->whereNotIn('id', $keptIds ?: [0])->get();
            $this->deleteRemovedImages($removed);
            $removed->each->delete();
        }
    }

    protected function syncVariantImages(Product $product, ProductVariant $variant, array $rows): void
    {
        $existing = $variant->images()->get()->keyBy('id');
        $keptIds = [];

        foreach ($rows as $index => $row) {
            $image = isset($row['id']) ? $existing->get((int) $row['id']) : new ProductImage;
            $image ??= new ProductImage;
            $previousPath = $image->image_path;
            $image->product()->associate($product);
            $image->variant()->associate($variant);
            $image->fill([
                'image_path' => $this->storeImageUpload($row['upload'] ?? null, $row['image_path'] ?? null, $product->id),
                'alt_text' => $row['alt_text'] ?? null,
                'sort_order' => $row['sort_order'] ?? $index,
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ]);
            $image->save();
            if ($previousPath !== $image->image_path) {
                $this->deletePublicPathIfUnused($previousPath, ProductImage::class, 'image_path', $image->id);
            }
            $keptIds[] = $image->id;
        }

        $this->deleteRemovedImages($variant->images()->whereNotIn('id', $keptIds ?: [0])->get());
        $variant->images()->whereNotIn('id', $keptIds ?: [0])->delete();
    }

    protected function resolveOptionValueIds(Collection $optionValues, array $row): array
    {
        $selectedIds = collect($row['option_value_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedIds->isNotEmpty() && $selectedIds->count() === collect($row['option_values'] ?? [])->count()) {
            return $selectedIds->filter(fn ($id) => $optionValues->has($id))->unique()->sort()->values()->all();
        }

        return collect($row['option_values'] ?? [])
            ->map(function (array $value) use ($optionValues) {
                return $optionValues->first(function (ProductOptionValue $optionValue) use ($value) {
                    return Str::lower($optionValue->productOption?->slug ?? '') === Str::lower($value['option_slug'] ?? '')
                        && Str::lower($optionValue->name) === Str::lower(trim((string) ($value['name'] ?? '')));
                })?->id;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function storeImageUpload(?UploadedFile $file, ?string $existingPath, int $productId): string
    {
        if ($file instanceof UploadedFile) {
            if (Str::startsWith((string) $existingPath, 'product-drafts/')) {
                Storage::disk('public')->delete($existingPath);
            }

            return $file->store("products/{$productId}", 'public');
        }

        if (Str::startsWith((string) $existingPath, 'product-drafts/')) {
            $destination = "products/{$productId}/".basename($existingPath);
            Storage::disk('public')->move($existingPath, $destination);

            return $destination;
        }

        return (string) $existingPath;
    }

    protected function storeOptionSwatch(?UploadedFile $file, ?string $existingPath, int $productId): ?string
    {
        if ($file instanceof UploadedFile) {
            if (Str::startsWith((string) $existingPath, 'product-drafts/')) {
                Storage::disk('public')->delete($existingPath);
            }

            return $file->store("products/{$productId}/swatches", 'public');
        }

        if (Str::startsWith((string) $existingPath, 'product-drafts/')) {
            $destination = "products/{$productId}/swatches/".basename($existingPath);
            Storage::disk('public')->move($existingPath, $destination);

            return $destination;
        }

        return $existingPath;
    }

    protected function deleteRemovedImages(Collection $images): void
    {
        foreach ($images as $image) {
            if (! $image instanceof ProductImage) {
                continue;
            }

            $path = $image->image_path;

            if (blank($path)) {
                continue;
            }

            $stillUsed = ProductImage::query()
                ->where('image_path', $path)
                ->whereKeyNot($image->id)
                ->exists();

            if (! $stillUsed) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    protected function deletePublicPathIfUnused(?string $path, string $modelClass, string $column, ?int $ignoreId = null): void
    {
        if (blank($path)) {
            return;
        }

        $query = $modelClass::query()->where($column, $path);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if (! $query->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    protected function uniqueDuplicateSku(string $sourceSku): string
    {
        $base = Str::upper($sourceSku).'-COPY';
        $candidate = $base;
        $suffix = 2;

        while (ProductVariant::query()->where('sku', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function uniqueRetiredSku(string $sourceSku): string
    {
        $base = Str::upper($sourceSku).'-RETIRED';
        $candidate = $base;
        $suffix = 2;

        while (ProductVariant::query()->where('sku', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
