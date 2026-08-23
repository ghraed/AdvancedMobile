<?php

namespace Tests\Feature;

use App\Enums\CompatibilityRuleType;
use App\Models\AccessoryCompatibilityRule;
use App\Models\Category;
use App\Models\DeviceProfile;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AccessoryCompatibilityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessoryCompatibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccessoryCompatibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AccessoryCompatibilityService::class);
    }

    public function test_device_profile_and_exact_compatibility_relationships_work(): void
    {
        $device = $this->device();
        $accessory = $this->accessory();
        $accessory->exactCompatibleDevices()->attach($device);

        $this->assertTrue($device->deviceProfile->is($device->deviceProfile()->first()));
        $this->assertTrue($accessory->exactCompatibleDevices->contains($device));
        $this->assertTrue($device->exactCompatibleAccessories->contains($accessory));
    }

    public function test_duplicate_exact_relation_is_rejected(): void
    {
        $device = $this->device();
        $accessory = $this->accessory();
        $accessory->exactCompatibleDevices()->attach($device);

        $this->expectException(QueryException::class);
        $accessory->exactCompatibleDevices()->attach($device);
    }

    public function test_exact_match_is_compatible_and_ranked_before_generic_match(): void
    {
        $device = $this->device();
        $generic = $this->accessory('Generic cable');
        $exact = $this->accessory('Exact case');
        AccessoryCompatibilityRule::factory()->create(['accessory_product_id' => $generic->id, 'rule_type' => CompatibilityRuleType::Connector, 'match_value' => 'USB-C']);
        $exact->exactCompatibleDevices()->attach($device);

        $result = $this->service->determine($exact, $device);
        $matches = $this->service->compatibleAccessoriesForDevice($device);

        $this->assertSame('compatible', $result['status']);
        $this->assertSame('exact', $result['match_type']);
        $this->assertSame([$exact->id, $generic->id], $matches->pluck('product.id')->all());
    }

    public function test_family_connector_charging_and_feature_rules_match(): void
    {
        $device = $this->device();

        foreach ([
            [CompatibilityRuleType::ModelIdentifier, 'TEST-1', 'model'],
            [CompatibilityRuleType::ModelFamily, 'Test Family', 'family'],
            [CompatibilityRuleType::Connector, 'usb-c', 'connector'],
            [CompatibilityRuleType::ChargingStandard, 'Qi2', 'charging_standard'],
            [CompatibilityRuleType::Feature, 'Wireless Charging', 'feature'],
        ] as [$ruleType, $value, $expected]) {
            $accessory = $this->accessory($expected);
            AccessoryCompatibilityRule::factory()->create(['accessory_product_id' => $accessory->id, 'rule_type' => $ruleType, 'match_value' => $value]);
            $this->assertSame($expected, $this->service->determine($accessory, $device)['match_type']);
        }
    }

    public function test_exclusion_overrides_exact_and_generic_rules(): void
    {
        $device = $this->device();
        $accessory = $this->accessory();
        $accessory->exactCompatibleDevices()->attach($device);
        $accessory->compatibilityExclusions()->attach($device);
        AccessoryCompatibilityRule::factory()->create(['accessory_product_id' => $accessory->id, 'rule_type' => CompatibilityRuleType::Connector, 'match_value' => 'USB-C']);

        $result = $this->service->determine($accessory, $device);

        $this->assertSame('incompatible', $result['status']);
        $this->assertSame('exclusion', $result['match_type']);
    }

    public function test_no_rule_returns_unknown_instead_of_incompatible(): void
    {
        $result = $this->service->determine($this->accessory(), $this->device());

        $this->assertSame('unknown', $result['status']);
        $this->assertFalse($result['compatible']);
    }

    public function test_compatible_accessory_list_eager_loads_without_an_n_plus_one_pattern(): void
    {
        $device = $this->device();
        foreach (range(1, 10) as $index) {
            $accessory = $this->accessory('Accessory '.$index);
            $accessory->exactCompatibleDevices()->attach($device);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $matches = $this->service->compatibleAccessoriesForDevice($device->fresh());
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(10, $matches);
        $this->assertLessThanOrEqual(15, $queryCount);
    }

    private function device(string $name = 'Test Phone'): Product
    {
        $product = Product::factory()->device()->create(['name' => $name]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 5, 'is_active' => true]);
        DeviceProfile::factory()->create([
            'product_id' => $product->id,
            'model_identifier' => 'TEST-1',
            'model_family' => 'Test Family',
            'connector_type' => 'USB-C',
            'charging_standards' => ['Qi2'],
            'features' => ['wireless charging'],
        ]);

        return $product;
    }

    private function accessory(string $name = 'Test Accessory'): Product
    {
        $product = Product::factory()->accessory('case')->create(['name' => $name]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 5, 'is_active' => true]);

        return $product;
    }
}
