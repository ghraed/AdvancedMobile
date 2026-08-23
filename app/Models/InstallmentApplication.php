<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InstallmentApplication extends Model
{
    use HasFactory;

    public const STATUSES = ['submitted', 'under_review', 'approved', 'rejected', 'cancelled', 'completed'];

    protected $guarded = [];

    protected $casts = ['product_price' => 'decimal:2', 'monthly_payment' => 'decimal:2', 'total_payable' => 'decimal:2', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(InstallmentApplicationDocument::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(InstallmentApplicationStatusHistory::class);
    }

    public function installmentAccount(): HasOne
    {
        return $this->hasOne(InstallmentAccount::class);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, match ($this->status) {
            'submitted' => ['under_review', 'cancelled'], 'under_review' => ['approved', 'rejected', 'cancelled'], 'approved' => ['completed', 'cancelled'], default => [],
        }, true);
    }
}
