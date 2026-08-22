<?php

namespace Database\Seeders;

use App\Models\InstallmentApplication;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Demo Admin', 'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
        $customer = User::query()->updateOrCreate(['email' => 'test@example.com'], [
            'name' => 'Maya Haddad', 'password' => 'password', 'role' => User::ROLE_CUSTOMER,
        ]);
        $secondCustomer = User::query()->updateOrCreate(['email' => 'rayan@example.com'], [
            'name' => 'Rayan Saad', 'password' => 'password', 'role' => User::ROLE_CUSTOMER,
        ]);

        $this->seedApplication($customer, $admin, 'DEMO-INS-1001', 'galaxy-s26-plus', 6, 'submitted', 'New customer application waiting for review.');
        $this->seedApplication($secondCustomer, $admin, 'DEMO-INS-1002', 'pixel-air-10', 3, 'under_review', 'Income documents are being reviewed.');
        $this->seedApplication($customer, $admin, 'DEMO-INS-1003', 'galaxy-a57', 9, 'approved', 'Approved for the selected payment plan.');
    }

    private function seedApplication(User $customer, User $admin, string $number, string $productSlug, int $payments, string $status, string $note): void
    {
        $product = Product::query()->where('slug', $productSlug)->firstOrFail();
        $variant = $product->variants()->available()->orderBy('price')->firstOrFail();
        $plan = $product->installmentPlans()->active()->where('product_variant_id', $variant->id)->where('number_of_payments', $payments)->firstOrFail();
        $options = $variant->optionValues()->with('productOption')->get()->keyBy(fn ($value) => $value->productOption?->slug);
        $isReviewed = $status !== 'submitted';
        $isApproved = $status === 'approved';

        $application = InstallmentApplication::query()->updateOrCreate(['application_number' => $number], [
            'user_id' => $customer->id,
            'first_name' => explode(' ', $customer->name, 2)[0],
            'last_name' => explode(' ', $customer->name, 2)[1] ?? 'Customer',
            'phone' => $customer->email === 'test@example.com' ? '+961 70 123 456' : '+961 71 654 321',
            'email' => $customer->email,
            'address' => 'Beirut, Lebanon',
            'identity_document_type' => 'lebanese_id',
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $product->name,
            'product_sku_snapshot' => $variant->sku,
            'brand_snapshot' => $product->brand,
            'storage_snapshot' => $options->get('storage')?->display_name,
            'color_snapshot' => $options->get('color')?->display_name,
            'product_price' => $variant->price,
            'installment_months' => $payments,
            'monthly_payment' => $plan->installment_amount,
            'total_payable' => $plan->total_amount,
            'currency' => 'USD',
            'status' => $status,
            'admin_notes' => $note,
            'reviewed_by' => $isReviewed ? $admin->id : null,
            'reviewed_at' => $isReviewed ? now()->subDay() : null,
            'approved_at' => $isApproved ? now()->subHours(12) : null,
        ]);

        $application->statusHistory()->delete();
        $application->statusHistory()->create(['from_status' => null, 'to_status' => 'submitted', 'note' => 'Application submitted for demo.', 'performed_by' => $customer->id, 'created_at' => now()->subDays(2)]);
        if ($status !== 'submitted') {
            $application->statusHistory()->create(['from_status' => 'submitted', 'to_status' => $status, 'note' => $note, 'performed_by' => $admin->id, 'created_at' => now()->subDay()]);
        }
    }
}
