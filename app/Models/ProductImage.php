<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_option_value_id',
        'product_variant_id',
        'image_path',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $image): void {
            if (! $image->is_primary) {
                return;
            }

            $query = self::query()->where('product_id', $image->product_id);

            if ($image->product_variant_id !== null) {
                $query->where('product_variant_id', $image->product_variant_id);
            } elseif ($image->product_option_value_id !== null) {
                $query->whereNull('product_variant_id')->where('product_option_value_id', $image->product_option_value_id);
            } else {
                $query->whereNull('product_variant_id');
                $query->whereNull('product_option_value_id');
            }

            if ($image->exists) {
                $query->whereKeyNot($image->id);
            }

            $query->update(['is_primary' => false]);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(ProductOptionValue::class, 'product_option_value_id');
    }

    public function getPathAttribute(): string
    {
        return $this->image_path;
    }

    public function setPathAttribute(string $value): void
    {
        $this->attributes['image_path'] = $value;
    }
}
