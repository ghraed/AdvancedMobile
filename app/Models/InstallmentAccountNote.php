<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentAccountNote extends Model
{
    protected $guarded = [];

    public function account(): BelongsTo
    {
        return $this->belongsTo(InstallmentAccount::class, 'installment_account_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
