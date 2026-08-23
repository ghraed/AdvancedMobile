<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\DeviceProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompatibilityAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_customer_cannot_edit_compatibility(): void
    {
        $accessory = Product::factory()->accessory()->create();
        $payload = $this->payload($accessory);

        $this->put(route('admin.products.update', $accessory), $payload)->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->customer()->create())
            ->put(route('admin.products.update', $accessory), $payload)->assertForbidden();
    }

    public function test_admin_can_add_multiple_devices_remove_matches_and_add_exclusion(): void
    {
        $admin = User::factory()->admin()->create();
        $accessory = Product::factory()->accessory('case')->create();
        $first = $this->device('First Phone');
        $second = $this->device('Second Phone');
        $payload = $this->payload($accessory) + ['compatibility' => [
            'exact_device_ids' => [$first->id, $second->id],
            'excluded_device_ids' => [$second->id],
            'rules' => ['connector' => 'USB-C'],
        ]];

        $this->actingAs($admin)->put(route('admin.products.update', $accessory), $payload)->assertRedirect();
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $accessory->fresh()->exactCompatibleDevices->modelKeys());
        $this->assertSame([$second->id], $accessory->fresh()->compatibilityExclusions->modelKeys());
        $this->assertDatabaseHas('accessory_compatibility_rules', ['accessory_product_id' => $accessory->id, 'rule_type' => 'connector', 'match_value' => 'USB-C']);

        $payload['compatibility']['exact_device_ids'] = [];
        $payload['compatibility']['excluded_device_ids'] = [];
        $payload['compatibility']['rules'] = [];
        $this->actingAs($admin)->put(route('admin.products.update', $accessory), $payload)->assertRedirect();
        $this->assertDatabaseMissing('accessory_compatible_products', ['accessory_product_id' => $accessory->id]);
    }

    public function test_validation_rejects_invalid_product_type_non_devices_and_self_links(): void
    {
        $admin = User::factory()->admin()->create();
        $accessory = Product::factory()->accessory()->create();

        $this->actingAs($admin)->from(route('admin.products.edit', $accessory))
            ->put(route('admin.products.update', $accessory), $this->payload($accessory, 'invalid'))
            ->assertSessionHasErrors('product_type');

        $payload = $this->payload($accessory) + ['compatibility' => ['exact_device_ids' => [$accessory->id]]];
        $this->actingAs($admin)->from(route('admin.products.edit', $accessory))
            ->put(route('admin.products.update', $accessory), $payload)
            ->assertSessionHasErrors('compatibility.exact_device_ids');
    }

    public function test_admin_can_save_a_structured_device_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $device = Product::factory()->create();
        $payload = $this->payload($device, 'device');
        $payload['accessory_subtype'] = null;
        $payload['device_profile'] = [
            'model_identifier' => 'IPHONE16,1',
            'model_family' => 'iPhone 15',
            'release_year' => 2023,
            'connector_type' => 'USB-C',
            'charging_standards' => 'MagSafe, Qi2',
            'features' => 'wireless charging',
        ];

        $this->actingAs($admin)->put(route('admin.products.update', $device), $payload)->assertRedirect();

        $profile = $device->fresh()->deviceProfile;
        $this->assertSame('IPHONE16,1', $profile->model_identifier);
        $this->assertSame(['MagSafe', 'Qi2'], $profile->charging_standards);
    }

    public function test_force_deleting_a_product_cleans_compatibility_relations(): void
    {
        $accessory = Product::factory()->accessory()->create();
        $device = $this->device();
        $accessory->exactCompatibleDevices()->attach($device);
        $accessory->compatibilityExclusions()->attach($device);

        $device->forceDelete();

        $this->assertDatabaseMissing('accessory_compatible_products', ['compatible_product_id' => $device->id]);
        $this->assertDatabaseMissing('accessory_compatibility_exclusions', ['product_id' => $device->id]);
        $this->assertDatabaseMissing('device_profiles', ['product_id' => $device->id]);
    }

    private function payload(Product $product, string $type = 'accessory'): array
    {
        return [
            'category_id' => $product->category_id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'status' => ProductStatus::Draft->value,
            'product_type' => $type,
            'accessory_subtype' => 'case',
        ];
    }

    private function device(string $name = 'Device'): Product
    {
        $device = Product::factory()->device()->create(['name' => $name]);
        DeviceProfile::factory()->create(['product_id' => $device->id]);

        return $device;
    }
}
