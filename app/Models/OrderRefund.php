<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OrderRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'reference', 'amount_cents', 'reason', 'restored_items',
        'refunded_by', 'refunded_by_name',
    ];

    protected $casts = ['restored_items' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Refund records are immutable.'));
        static::deleting(fn () => throw new LogicException('Refund records are immutable.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
