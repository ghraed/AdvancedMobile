<?php

namespace App\Models;

use App\Enums\CompatibilityRuleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessoryCompatibilityRule extends Model
{
    use HasFactory;

    protected $fillable = ['accessory_product_id', 'rule_type', 'match_value'];

    protected $casts = ['rule_type' => CompatibilityRuleType::class];

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'accessory_product_id');
    }
}
