<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUnitEvent extends Model
{
    protected $fillable = ['actor_id', 'event_type', 'changes'];
    protected $casts = ['changes' => 'array'];
    public function deviceUnit(): BelongsTo { return $this->belongsTo(DeviceUnit::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
