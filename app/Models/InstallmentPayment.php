<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentPayment extends Model
{
    public const METHODS = ['cash', 'card', 'bank_transfer', 'other'];

    protected $guarded = [];

    protected $casts = ['paid_at' => 'datetime', 'reversed_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstallmentAccount::class, 'installment_account_id');
    }

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(InstallmentScheduleItem::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InstallmentPaymentAllocation::class);
    }

    public function getIsReversedAttribute(): bool
    {
        return $this->reversed_at !== null;
    }
}
