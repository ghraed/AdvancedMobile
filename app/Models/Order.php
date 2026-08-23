<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'sales_channel', 'user_id', 'cashier_id', 'cashier_name',
        'pending_purchase_session_id', 'idempotency_key', 'product_id',
        'product_variant_id', 'installment_plan_id', 'quantity', 'status',
        'product_name', 'sku', 'storage', 'color', 'variant_price',
        'financing_fee', 'amount_due_today', 'total_financed_amount',
        'total_amount', 'future_payment_count', 'interval_type', 'customer_snapshot',
        'subtotal_cents', 'discount_type', 'discount_value', 'discount_cents',
        'total_cents', 'payment_status', 'refunded_at',
    ];

    protected $casts = [
        'customer_snapshot' => 'array',
        'variant_price' => 'decimal:2', 'financing_fee' => 'decimal:2', 'amount_due_today' => 'decimal:2',
        'total_financed_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(OrderInstallment::class)->orderBy('sequence');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(OrderRefund::class);
    }

    public function isPosSale(): bool
    {
        return $this->sales_channel === 'pos';
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format(($this->total_cents ?? 0) / 100, 2);
    }
}
