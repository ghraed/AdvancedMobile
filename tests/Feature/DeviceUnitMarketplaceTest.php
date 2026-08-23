<?php

namespace Tests\Feature;

use App\Enums\DeviceConditionGrade;
use App\Enums\DeviceConditionType;
use App\Enums\DeviceUnitStatus;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\DeviceUnit;
use App\Models\InstallmentPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\DeviceInventoryService;
use App\Services\InstallmentPlanService;
use App\Services\OrderCreationService;
use App\Services\PendingPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class DeviceUnitMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_identifier_is_normalized_hashed_encrypted_and_hidden_from_serialization(): void
    {
        [, $variant] = $this->catalog();
        $unit = DeviceUnit::factory()->create(['product_variant_id' => $variant->id, 'imei' => '49-015420-323751-8', 'serial_number' => 'SER-1234', 'acquisition_cost_cents' => 50000, 'refurbishment_notes' => 'private']);
        $raw = DB::table('device_units')->find($unit->id);

        $this->assertSame(hash('sha256', '490154203237518'), $raw->imei_hash);
        $this->assertNotSame('490154203237518', $raw->imei_encrypted);
        $this->assertSame('490154203237518', $unit->fresh()->imei);
        $json = $unit->fresh()->toJson();
        $this->assertStringNotContainsString('490154203237518', $json);
        $this->assertStringNotContainsString('SER1234', $json);
        $this->assertStringNotContainsString('private', $json);
        $this->assertStringNotContainsString('50000', $json);
    }

    public function test_database_rejects_duplicate_normalized_imei(): void
    {
        [, $variant] = $this->catalog();
        DeviceUnit::factory()->create(['product_variant_id' => $variant->id, 'imei' => '490154203237518']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DeviceUnit::factory()->create(['product_variant_id' => $variant->id, 'imei' => '49 015420 323751 8']);
    }

    public function test_admin_access_validation_upload_edit_and_retire_flow(): void
    {
        Storage::fake('public');
        [, $variant] = $this->catalog();
        $payload = $this->payload($variant, ['images' => [UploadedFile::fake()->image('front.jpg')]]);

        $this->post(route('admin.device-units.store'), $payload)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->customer()->create())->post(route('admin.device-units.store'), $payload)->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.device-units.store'), $payload)->assertRedirect();
        $unit = DeviceUnit::firstOrFail();
        $this->assertCount(1, $unit->images);
        Storage::disk('public')->assertExists($unit->images->first()->image_path);

        $this->put(route('admin.device-units.update', $unit), $this->payload($variant, ['battery_health_percent' => 101]))->assertSessionHasErrors('battery_health_percent');
        $this->put(route('admin.device-units.update', $unit), $this->payload($variant, ['battery_health_percent' => 91, 'condition_grade' => 'a']))->assertRedirect();
        $this->assertSame(91, $unit->fresh()->battery_health_percent);
        $this->patch(route('admin.device-units.retire', $unit))->assertRedirect();
        $this->assertSame(DeviceUnitStatus::Retired, $unit->fresh()->status);
        $this->assertSame(0, $variant->fresh()->stock_quantity);
    }

    public function test_admin_rejects_invalid_imei_duplicate_condition_and_battery(): void
    {
        [, $variant] = $this->catalog();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.device-units.store'), $this->payload($variant))->assertRedirect();
        $this->post(route('admin.device-units.store'), $this->payload($variant))->assertSessionHasErrors('imei');
        $this->post(route('admin.device-units.store'), $this->payload($variant, ['imei' => '123', 'condition_type' => 'broken', 'battery_health_percent' => -1]))
            ->assertSessionHasErrors(['imei', 'condition_type', 'battery_health_percent']);
    }

    public function test_public_page_displays_condition_defects_battery_warranty_and_exact_photo_without_private_data(): void
    {
        Storage::fake('public');
        [$product, $variant] = $this->catalog();
        $unit = app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($variant, [
            'known_defects' => ['Small scratch near camera'], 'battery_health_percent' => 87,
            'warranty_days' => 90, 'acquisition_cost' => '500.00', 'refurbishment_notes' => 'supplier secret',
            'serial_number' => 'SERIAL-PRIVATE', 'images' => [UploadedFile::fake()->image('exact.jpg')],
        ]));

        $response = $this->get(route('device-units.show', [$product, $unit]));
        $response->assertOk()->assertSeeText('Used')->assertSeeText('B / Very Good')->assertSeeText('87%')
            ->assertSeeText('3-month shop warranty')->assertSeeText('Small scratch near camera')->assertSee('device-units\\/'.$unit->id, false)
            ->assertDontSee('490154203237518')->assertDontSee('SERIAL-PRIVATE')->assertDontSee('supplier secret')->assertDontSee('50000');
    }

    public function test_visibility_landing_pages_and_filters_only_include_available_matching_units(): void
    {
        [$usedProduct, $usedVariant] = $this->catalog('Used Phone');
        app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($usedVariant, ['battery_health_percent' => 92]));
        [$refurbishedProduct, $refurbishedVariant] = $this->catalog('Refurbished Phone');
        app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($refurbishedVariant, ['imei' => '356938035643809', 'condition_type' => 'refurbished', 'battery_health_percent' => 82]));

        $this->get(route('used-phones.index'))->assertOk()->assertSeeText($usedProduct->name)->assertDontSeeText($refurbishedProduct->name);
        $this->get('/catalog?condition=refurbished&grade=b&battery_min=80')->assertOk()->assertSeeText($refurbishedProduct->name)->assertDontSeeText($usedProduct->name);
        $usedVariant->update(['is_active' => false]);
        $this->get(route('device-units.show', [$usedProduct, $usedVariant->deviceUnits()->first()]))->assertNotFound();
    }

    public function test_unit_photo_has_priority_and_falls_back_to_catalog_images(): void
    {
        Storage::fake('public');
        [$product, $variant] = $this->catalog();
        $unit = app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($variant, ['images' => [UploadedFile::fake()->image('exact.jpg')]]));
        $resolved = app(\App\Services\ProductImageResolver::class)->resolve($product, $variant, $unit);
        $this->assertStringContainsString('device-units/'.$unit->id, $resolved->first()->image_path);
        $unit->images()->delete();
        $fallback = \App\Models\ProductImage::factory()->create(['product_id' => $product->id, 'product_variant_id' => null, 'image_path' => 'products/fallback.jpg']);
        $this->assertSame($fallback->id, app(\App\Services\ProductImageResolver::class)->resolve($product->fresh(), $variant->fresh(), $unit->fresh())->first()->id);
    }

    public function test_pending_session_reserves_exact_unit_and_order_sells_it_once_with_secure_snapshot_and_unit_price(): void
    {
        [$product, $variant] = $this->catalog();
        $unit = app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($variant, [
            'selling_price_override' => '750.00', 'acquisition_cost' => '420.00', 'installments_enabled' => true,
        ]));
        $plan = InstallmentPlan::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'number_of_payments' => 3, 'total_amount' => 900, 'is_active' => true]);
        $calculation = app(InstallmentPlanService::class)->previewFromPayload($plan->toArray(), 750, null, true);
        $token = app(PendingPurchaseService::class)->create($product, [
            'variant_id' => $variant->id, 'device_unit_id' => $unit->id, 'option_value_ids' => [], 'plan_id' => $plan->id,
            'amount_due_now' => $calculation['amount_due_now'], 'total_amount' => $calculation['total_amount'],
        ]);
        $this->assertSame(DeviceUnitStatus::Reserved, $unit->fresh()->status);
        $this->assertSame(0, $variant->fresh()->stock_quantity);

        $user = User::factory()->customer()->create();
        $order = app(OrderCreationService::class)->create($user, $token);
        $item = $order->items()->firstOrFail();
        $this->assertSame(DeviceUnitStatus::Sold, $unit->fresh()->status);
        $this->assertSame($unit->id, $item->device_unit_id);
        $this->assertSame(75000, $item->unit_price_cents);
        $this->assertSame(42000, $item->unit_cost_cents);
        $this->assertSame('used', $item->device_snapshot['condition']);
        $this->assertArrayNotHasKey('imei', $item->device_snapshot);
        $this->assertArrayNotHasKey('serial_number', $item->device_snapshot);
        $this->assertSame('750.00', (string) $order->variant_price);
        $this->assertSame($order->id, app(OrderCreationService::class)->create($user, $token)->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_returned_or_reserved_unit_is_not_public_or_purchasable(): void
    {
        [$product, $variant] = $this->catalog();
        $unit = app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($variant, ['status' => 'returned']));
        $this->get(route('device-units.show', [$product, $unit]))->assertNotFound();
        $this->get('/catalog')->assertDontSeeText($product->name);
    }

    public function test_expired_reservation_returns_to_available_but_returned_device_does_not(): void
    {
        [, $variant] = $this->catalog();
        $unit = app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($variant));
        app(DeviceInventoryService::class)->reserve($unit, 'reservation-token', now()->subMinute());
        $this->assertSame(1, app(DeviceInventoryService::class)->releaseExpiredReservations());
        $this->assertSame(DeviceUnitStatus::Available, $unit->fresh()->status);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
        $unit->update(['status' => DeviceUnitStatus::Returned]);
        app(DeviceInventoryService::class)->syncVariantStock($variant->fresh());
        $this->assertSame(0, $variant->fresh()->stock_quantity);
    }

    public function test_second_session_cannot_reserve_same_device_and_failed_order_rolls_back(): void
    {
        [$product, $variant] = $this->catalog();
        $unit = app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($variant, ['installments_enabled' => true]));
        $plan = InstallmentPlan::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'number_of_payments' => 3, 'total_amount' => 900]);
        $preview = ['variant_id' => $variant->id, 'device_unit_id' => $unit->id, 'option_value_ids' => [], 'plan_id' => $plan->id, 'amount_due_now' => 300, 'total_amount' => 900];
        $token = app(PendingPurchaseService::class)->create($product, $preview);
        try { app(PendingPurchaseService::class)->create($product, $preview); $this->fail('A second reservation should fail.'); } catch (\DomainException $exception) { $this->assertStringContainsString('no longer available', $exception->getMessage()); }

        Event::listen('eloquent.creating: '.Order::class, fn () => throw new RuntimeException('forced failure'));
        try { app(OrderCreationService::class)->create(User::factory()->customer()->create(), $token); } catch (RuntimeException) {}
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(DeviceUnitStatus::Reserved, $unit->fresh()->status);
    }

    public function test_server_rejects_installments_for_unsupported_unit_and_ignores_browser_price(): void
    {
        [$product, $variant] = $this->catalog();
        $unit = app(DeviceInventoryService::class)->save(new DeviceUnit, $this->payload($variant, ['selling_price_override' => '750.00', 'installments_enabled' => false]));
        foreach ([3, 6, 9] as $payments) InstallmentPlan::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'number_of_payments' => $payments, 'total_amount' => 900]);
        $plan = $product->installmentPlans()->where('number_of_payments', 3)->firstOrFail();
        $this->postJson(route('products.purchase-preview', $product), ['variant_id' => $variant->id, 'device_unit_id' => $unit->id, 'plan_id' => $plan->id, 'total_amount' => 1])->assertUnprocessable();

        $unit->update(['installments_enabled' => true]);
        $response = $this->postJson(route('products.purchase-preview', $product), ['variant_id' => $variant->id, 'device_unit_id' => $unit->id, 'plan_id' => $plan->id, 'total_amount' => 1]);
        $response->assertOk()->assertJsonPath('variant_price', 750)->assertJsonPath('total_amount', 750);
    }

    private function catalog(string $name = 'Traceable Phone'): array
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->device()->create(['category_id' => $category->id, 'status' => ProductStatus::Active, 'name' => $name]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 900, 'stock_quantity' => 0, 'is_unit_managed' => true, 'is_active' => true, 'option_signature' => '']);
        return [$product, $variant];
    }

    private function payload(ProductVariant $variant, array $overrides = []): array
    {
        return array_replace([
            'product_variant_id' => $variant->id, 'condition_type' => DeviceConditionType::Used->value,
            'condition_grade' => DeviceConditionGrade::B->value, 'imei' => '490154203237518',
            'battery_health_percent' => 88, 'known_defects' => [], 'condition_checklist' => ['screen' => 'tested_ok'],
            'included_accessories' => ['cable'], 'parts_replaced' => [], 'installments_enabled' => false,
            'warranty_days' => 90, 'status' => DeviceUnitStatus::Available->value,
        ], $overrides);
    }
}
