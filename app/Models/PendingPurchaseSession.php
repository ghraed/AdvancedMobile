<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingPurchaseSession extends Model
{
    use HasFactory;

    protected $fillable = ['token_hash', 'user_id', 'product_id', 'product_variant_id', 'device_unit_id', 'option_value_ids', 'installment_plan_id', 'amount_due_today', 'total_amount', 'return_url', 'scheduled_at', 'expires_at'];

    protected $casts = [
        'option_value_ids' => 'array', 'amount_due_today' => 'decimal:2', 'total_amount' => 'decimal:2',
        'scheduled_at' => 'immutable_datetime', 'expires_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function deviceUnit(): BelongsTo { return $this->belongsTo(DeviceUnit::class); }
    public function installmentPlan(): BelongsTo { return $this->belongsTo(InstallmentPlan::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function isExpired(): bool { return $this->expires_at->isPast(); }
}
