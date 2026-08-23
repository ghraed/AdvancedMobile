<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\DeviceProfile;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompatibilityStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_page_groups_visible_compatible_accessories_and_hides_unavailable_ones(): void
    {
        $device = $this->device();
        $visible = $this->accessory('Visible Case', ['accessory_subtype' => 'case']);
        $draft = $this->accessory('Draft Case', ['status' => ProductStatus::Draft]);
        $outOfStock = $this->accessory('Empty Case', [], 0);
        $inactiveCategory = Category::factory()->create(['is_active' => false]);
        $inactiveCategoryAccessory = $this->accessory('Hidden Category Case', ['category_id' => $inactiveCategory->id]);
        $incompatible = $this->accessory('Unmatched Case');
        foreach ([$visible, $draft, $outOfStock, $inactiveCategoryAccessory] as $accessory) {
            $accessory->exactCompatibleDevices()->attach($device);
        }

        $this->get(route('products.show', $device))
            ->assertOk()
            ->assertSeeText('Compatible Accessories')
            ->assertSeeText('Cases')
            ->assertSeeText('Visible Case')
            ->assertDontSeeText('Draft Case')
            ->assertDontSeeText('Empty Case')
            ->assertDontSeeText('Hidden Category Case')
            ->assertDontSeeText('Unmatched Case');
    }

    public function test_compatible_accessories_listing_filters_and_paginates_with_query_params(): void
    {
        $device = $this->device();
        foreach (range(1, 13) as $index) {
            $accessory = $this->accessory('Case '.$index, ['accessory_subtype' => 'case']);
            $accessory->exactCompatibleDevices()->attach($device);
        }

        $this->get(route('accessories.compatible', ['device' => $device->id, 'subtype' => 'case']))
            ->assertOk()->assertSee('device='.$device->id, false)->assertSee('subtype=case', false)
            ->assertSeeText('13 compatible');
        $this->get(route('accessories.compatible', ['device' => $device->id, 'subtype' => 'case', 'page' => 2]))
            ->assertOk()->assertSeeText('Case 9');
    }

    public function test_accessory_page_lists_devices_and_checker_distinguishes_all_three_states(): void
    {
        $compatible = $this->device('Compatible Phone');
        $excluded = $this->device('Excluded Phone');
        $unknown = $this->device('Unknown Phone');
        $accessory = $this->accessory('Checker Accessory');
        $accessory->exactCompatibleDevices()->attach($compatible);
        $accessory->compatibilityExclusions()->attach($excluded);

        $this->get(route('products.show', $accessory))
            ->assertOk()->assertSeeText('Compatible With')->assertSeeText('Compatible Phone')
            ->assertSeeText('Check compatibility with your phone');

        $this->postJson(route('products.check-compatibility', $accessory), ['device_id' => $compatible->id])
            ->assertOk()->assertJsonPath('status', 'compatible');
        $this->postJson(route('products.check-compatibility', $accessory), ['device_id' => $excluded->id])
            ->assertOk()->assertJsonPath('status', 'incompatible');
        $this->postJson(route('products.check-compatibility', $accessory), ['device_id' => $unknown->id])
            ->assertOk()->assertJsonPath('status', 'unknown');
    }

    private function device(string $name = 'Device'): Product
    {
        $product = Product::factory()->device()->create(['name' => $name]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 5]);
        DeviceProfile::factory()->create(['product_id' => $product->id, 'model_identifier' => 'ID-'.$product->id]);

        return $product;
    }

    private function accessory(string $name, array $attributes = [], int $stock = 5): Product
    {
        $product = Product::factory()->accessory('case')->create(['name' => $name] + $attributes);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => $stock]);

        return $product;
    }
}
