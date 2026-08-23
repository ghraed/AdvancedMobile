<?php

namespace Database\Factories;

use App\Enums\DeviceConditionGrade;
use App\Enums\DeviceConditionType;
use App\Enums\DeviceUnitStatus;
use App\Models\DeviceUnit;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceUnitFactory extends Factory
{
    protected $model = DeviceUnit::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory()->state(['is_unit_managed' => true, 'stock_quantity' => 0]),
            'condition_type' => DeviceConditionType::Used,
            'condition_grade' => DeviceConditionGrade::B,
            'imei' => fake()->unique()->numerify('###############'),
            'battery_health_percent' => 88,
            'known_defects' => [],
            'condition_checklist' => ['screen' => 'tested_ok'],
            'included_accessories' => ['cable'],
            'installments_enabled' => false,
            'status' => DeviceUnitStatus::Available,
        ];
    }
}
