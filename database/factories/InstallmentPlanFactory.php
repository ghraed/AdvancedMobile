<?php

namespace Database\Factories;

use App\Models\InstallmentPlan;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentPlanFactory extends Factory
{
    protected $model = InstallmentPlan::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'variant_key' => null,
            'number_of_payments' => fake()->randomElement([3, 6, 9]),
            'down_payment' => 0,
            'financing_fee' => 0,
            'installment_amount' => 100,
            'total_amount' => 300,
            'interval_type' => 'monthly',
            'is_active' => true,
        ];
    }
}
