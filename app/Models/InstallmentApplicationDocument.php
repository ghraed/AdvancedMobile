<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentApplicationDocument extends Model
{
    public $timestamps = true;

    protected $guarded = [];

    protected $casts = ['uploaded_at' => 'datetime'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(InstallmentApplication::class, 'installment_application_id');
    }
}
