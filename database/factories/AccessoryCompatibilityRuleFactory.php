<?php

namespace Database\Factories;

use App\Enums\CompatibilityRuleType;
use App\Models\AccessoryCompatibilityRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccessoryCompatibilityRuleFactory extends Factory
{
    protected $model = AccessoryCompatibilityRule::class;

    public function definition(): array
    {
        return [
            'accessory_product_id' => Product::factory()->accessory(),
            'rule_type' => CompatibilityRuleType::Connector,
            'match_value' => 'USB-C',
        ];
    }
}
