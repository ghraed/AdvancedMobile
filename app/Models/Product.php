<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\AccessorySubtype;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'specifications',
        'brand',
        'product_type',
        'accessory_subtype',
        'status',
        'is_featured',
        'is_trending',
        'published_at',
        'offer_ends_at',
    ];

    protected $casts = [
        'specifications' => 'array',
        'status' => ProductStatus::class,
        'product_type' => ProductType::class,
        'accessory_subtype' => AccessorySubtype::class,
        'is_featured' => 'boolean',
        'is_trending' => 'boolean',
        'published_at' => 'datetime',
        'offer_ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            if (blank($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productOptions(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order')->orderBy('name');
    }

    public function optionValues(): HasManyThrough
    {
        return $this->hasManyThrough(ProductOptionValue::class, ProductOption::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function storageValues(): HasManyThrough
    {
        return $this->optionValues()->whereHas('productOption', fn (Builder $query) => $query->where('slug', ProductOption::STORAGE_SLUG));
    }

    public function storageOptions(): HasManyThrough
    {
        return $this->storageValues();
    }

    public function colorValues(): HasManyThrough
    {
        return $this->optionValues()->whereHas('productOption', fn (Builder $query) => $query->where('slug', ProductOption::COLOR_SLUG));
    }

    public function colorOptions(): HasManyThrough
    {
        return $this->colorValues();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function deviceUnits(): HasManyThrough
    {
        return $this->hasManyThrough(DeviceUnit::class, ProductVariant::class, 'product_id', 'product_variant_id');
    }

    public function deviceProfile(): HasOne
    {
        return $this->hasOne(DeviceProfile::class);
    }

    public function compatibilityRules(): HasMany
    {
        return $this->hasMany(AccessoryCompatibilityRule::class, 'accessory_product_id');
    }

    public function exactCompatibleDevices(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'accessory_compatible_products', 'accessory_product_id', 'compatible_product_id')->withTimestamps();
    }

    public function exactCompatibleAccessories(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'accessory_compatible_products', 'compatible_product_id', 'accessory_product_id')->withTimestamps();
    }

    public function compatibilityExclusions(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'accessory_compatibility_exclusions', 'accessory_product_id', 'product_id')->withTimestamps();
    }

    public function excludedFromAccessories(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'accessory_compatibility_exclusions', 'product_id', 'accessory_product_id')->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->whereNull('product_variant_id')->orderBy('sort_order')->orderByDesc('is_primary');
    }

    public function generalImages(): HasMany
    {
        return $this->images()->whereNull('product_option_value_id');
    }

    public function colorImages(): HasMany
    {
        return $this->images()->whereNotNull('product_option_value_id');
    }

    public function installmentPlans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class)->orderBy('number_of_payments');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->latest('id');
    }

    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', ProductStatus::Active)
            ->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('is_active', true))
            ->whereHas('variants', fn (Builder $variantQuery) => $variantQuery->active()->inStock());
    }

    public function scopeActiveAvailable(Builder $query): Builder
    {
        return $this->scopePubliclyAvailable($query);
    }
}
