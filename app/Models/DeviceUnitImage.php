<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceUnitImage extends Model
{
    protected $fillable = ['device_unit_id', 'image_path', 'view_type', 'alt_text', 'sort_order', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];
    public function deviceUnit(): BelongsTo { return $this->belongsTo(DeviceUnit::class); }
}
