<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentAccountEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstallmentAccount::class, 'installment_account_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
