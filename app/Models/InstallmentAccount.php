<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallmentAccount extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    protected $guarded = [];

    protected $casts = [
        'first_due_date' => 'date',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(InstallmentApplication::class, 'installment_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(InstallmentScheduleItem::class)->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(InstallmentAccountNote::class)->latest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(InstallmentAccountEvent::class)->latest('created_at');
    }

    public function getRemainingBalanceCentsAttribute(): int
    {
        return max(0, $this->total_payable_cents - $this->amount_paid_cents);
    }

    public function getOverdueAmountCentsAttribute(): int
    {
        return $this->scheduleItems->sum(fn (InstallmentScheduleItem $item) => $item->isOverdue() ? $item->remaining_cents : 0);
    }

    public function getNextScheduleItemAttribute(): ?InstallmentScheduleItem
    {
        return $this->scheduleItems->first(fn (InstallmentScheduleItem $item) => $item->remaining_cents > 0 && $item->effective_status !== 'cancelled');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('account_status', self::STATUS_ACTIVE)
            ->whereHas('scheduleItems', fn (Builder $items) => $items
                ->whereDate('due_date', '<', today())
                ->whereColumn('amount_paid_cents', '<', 'amount_due_cents'));
    }
}
