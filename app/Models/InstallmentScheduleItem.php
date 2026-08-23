<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentScheduleItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    protected $appends = ['remaining_cents', 'effective_status'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstallmentAccount::class, 'installment_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InstallmentPaymentAllocation::class);
    }

    public function getRemainingCentsAttribute(): int
    {
        return max(0, $this->amount_due_cents - $this->amount_paid_cents);
    }

    public function isOverdue(): bool
    {
        return $this->remaining_cents > 0
            && $this->due_date->isBefore(today())
            && ($this->account?->account_status ?? InstallmentAccount::STATUS_ACTIVE) !== InstallmentAccount::STATUS_CANCELLED;
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->remaining_cents === 0) {
            return 'paid';
        }
        if (($this->account?->account_status ?? null) === InstallmentAccount::STATUS_CANCELLED || $this->status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->isOverdue()) {
            return 'overdue';
        }
        if ($this->amount_paid_cents > 0) {
            return 'partial';
        }

        return $this->due_date->isSameDay(today()) ? 'due' : 'upcoming';
    }
}
