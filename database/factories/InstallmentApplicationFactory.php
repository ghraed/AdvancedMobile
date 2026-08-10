<?php

namespace Database\Factories;

use App\Models\InstallmentApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentApplicationFactory extends Factory
{
    protected $model = InstallmentApplication::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'application_number' => 'INS-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'), 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'phone' => '+9613123456', 'email' => fake()->safeEmail(), 'address' => fake()->address(), 'identity_document_type' => 'lebanese_id', 'product_name_snapshot' => 'Phone', 'product_price' => '900.00', 'installment_months' => 3, 'monthly_payment' => '300.00', 'total_payable' => '900.00', 'currency' => 'USD', 'status' => 'submitted'];
    }
}
