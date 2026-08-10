<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['reference', 'user_id', 'pending_purchase_session_id', 'product_id', 'product_variant_id', 'installment_plan_id', 'quantity', 'status', 'product_name', 'sku', 'storage', 'color', 'variant_price', 'financing_fee', 'amount_due_today', 'total_financed_amount', 'total_amount', 'future_payment_count', 'interval_type', 'customer_snapshot'];

    protected $casts = [
        'customer_snapshot' => 'array',
        'variant_price' => 'decimal:2', 'financing_fee' => 'decimal:2', 'amount_due_today' => 'decimal:2',
        'total_financed_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function installments(): HasMany { return $this->hasMany(OrderInstallment::class)->orderBy('sequence'); }
}
