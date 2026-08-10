<?php

namespace Tests\Feature;

use App\Models\InstallmentApplication;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallmentApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_application_uses_server_price_and_stores_private_documents(): void
    {
        Storage::fake('local');
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => '900.00', 'stock_quantity' => 2]);
        $file = UploadedFile::fake()->image('id.jpg');

        $response = $this->post(route('installments.store'), $this->payload($product, $variant, [
            'product_price' => '1.00',
            'id_front' => $file,
            'id_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie_with_id' => UploadedFile::fake()->image('selfie.jpg'),
            'proof_of_address' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ]));

        $response->assertRedirect();
        $application = InstallmentApplication::firstOrFail();
        $this->assertSame('900.00', $application->product_price);
        $this->assertSame('300.00', $application->monthly_payment);
        $this->assertCount(4, $application->documents);
        Storage::disk('local')->assertExists($application->documents->first()->stored_path);
    }

    public function test_another_customer_cannot_view_an_application_or_document(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $application = InstallmentApplication::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('installments.show', $application))->assertForbidden();
    }

    public function test_non_admin_cannot_access_admin_applications(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->get(route('admin.installment-applications.index'))
            ->assertForbidden();
    }

    private function payload(Product $product, ProductVariant $variant, array $overrides = []): array
    {
        return array_merge(['product_id' => $product->id, 'variant_id' => $variant->id, 'installment_months' => 3, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '03123456', 'email' => 'ada@example.test', 'address' => 'Jounieh, Lebanon', 'identity_document_type' => 'lebanese_id'], $overrides);
    }
}
