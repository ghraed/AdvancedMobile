<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInstallment extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'sequence', 'amount', 'due_date', 'status'];
    protected $casts = ['amount' => 'decimal:2', 'due_date' => 'date'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
