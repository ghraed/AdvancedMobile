<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_at_price',
        'stock_quantity',
        'is_active',
        'option_signature',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_value')->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderByDesc('is_primary');
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class)->orderBy('number_of_payments');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->inStock();
    }

    public function syncOptionValues(array $optionValueIds): void
    {
        sort($optionValueIds);
        $this->forceFill([
            'option_signature' => self::buildOptionSignature($optionValueIds),
        ])->save();

        $this->optionValues()->sync($optionValueIds);
    }

    public static function buildOptionSignature(array $optionValueIds): string
    {
        sort($optionValueIds);

        return implode('|', $optionValueIds);
    }

    public function storageValue(): ?ProductOptionValue
    {
        return $this->optionValues->first(fn (ProductOptionValue $value) => $value->productOption?->slug === ProductOption::STORAGE_SLUG);
    }

    public function colorValue(): ?ProductOptionValue
    {
        return $this->optionValues->first(fn (ProductOptionValue $value) => $value->productOption?->slug === ProductOption::COLOR_SLUG);
    }

    public function getStorageOptionAttribute(): ?ProductOptionValue
    {
        return $this->relationLoaded('optionValues') ? $this->storageValue() : $this->optionValues()->with('productOption')->get()->first(
            fn (ProductOptionValue $value) => $value->productOption?->slug === ProductOption::STORAGE_SLUG
        );
    }

    public function getColorOptionAttribute(): ?ProductOptionValue
    {
        return $this->relationLoaded('optionValues') ? $this->colorValue() : $this->optionValues()->with('productOption')->get()->first(
            fn (ProductOptionValue $value) => $value->productOption?->slug === ProductOption::COLOR_SLUG
        );
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->is_active && $this->stock_quantity > 0;
    }
}
