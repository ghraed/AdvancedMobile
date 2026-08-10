<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'variant_key',
        'number_of_payments',
        'down_payment',
        'financing_fee',
        'installment_amount',
        'total_amount',
        'interval_type',
        'is_active',
    ];

    protected $casts = [
        'down_payment' => 'decimal:2',
        'financing_fee' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getMonthsAttribute(): int
    {
        return $this->number_of_payments;
    }

    public function setMonthsAttribute(int $value): void
    {
        $this->attributes['number_of_payments'] = $value;
    }

    public function getMonthlyAmountAttribute(): string
    {
        return $this->installment_amount;
    }

    public function setMonthlyAmountAttribute(float|string $value): void
    {
        $this->attributes['installment_amount'] = $value;
    }

    public function getScopeLabelAttribute(): string
    {
        if ($this->product_variant_id === null) {
            return 'All variants';
        }

        $variant = $this->relationLoaded('variant') ? $this->variant : null;

        return $variant?->sku ? 'Variant: '.$variant->sku : 'Variant-specific';
    }
}
