<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentPaymentAllocation extends Model
{
    protected $guarded = [];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(InstallmentPayment::class, 'installment_payment_id');
    }

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(InstallmentScheduleItem::class, 'installment_schedule_item_id');
    }
}
