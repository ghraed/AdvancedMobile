<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'model_identifier', 'model_family', 'release_year', 'connector_type',
        'charging_standards', 'features', 'metadata',
    ];

    protected $casts = [
        'release_year' => 'integer',
        'charging_standards' => 'array',
        'features' => 'array',
        'metadata' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
