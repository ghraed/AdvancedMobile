<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'device_unit_id', 'category_id', 'product_name', 'brand',
        'category_name', 'sku', 'barcode', 'variant_options', 'device_snapshot', 'unit_price_cents', 'unit_cost_cents', 'quantity',
        'subtotal_cents', 'discount_cents', 'total_cents',
    ];

    protected $casts = [
        'variant_options' => 'array',
        'device_snapshot' => 'array',
        'unit_price_cents' => 'integer',
        'unit_cost_cents' => 'integer',
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'total_cents' => 'integer',
        'quantity' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Financial order item snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Financial order item snapshots are immutable.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function deviceUnit(): BelongsTo { return $this->belongsTo(DeviceUnit::class); }
}
