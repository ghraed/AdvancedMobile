<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\InstallmentPlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categoryDefinitions = [
            ['Mobiles & Tablets', 'mobiles-tablets', 'smartphone'],
            ['Vouchers & Gift Cards', 'vouchers-gift-cards', 'confirmation_number'],
            ['Kitchen Appliances', 'kitchen-appliances', 'countertops'],
            ['Laundry & Garment Care', 'laundry-garment-care', 'local_laundry_service'],
            ['Heating, Cooling & Air Quality', 'heating-cooling-air-quality', 'ac_unit'],
            ['Floor & Carpet Care', 'floor-carpet-care', 'vacuum'],
            ['Gaming', 'gaming', 'sports_esports'],
            ['TV & Audio', 'tv-audio', 'tv'],
            ['Outdoor Living & Leisure', 'outdoor-living-leisure', 'outdoor_garden'],
        ];

        foreach ($categoryDefinitions as $index => [$name, $slug, $icon]) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                ['parent_id' => null, 'name' => $name, 'icon' => $icon, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }

        $mobiles = Category::query()->where('slug', 'mobiles-tablets')->firstOrFail();
        $smartphones = Category::query()->updateOrCreate(
            ['slug' => 'smartphones'],
            ['parent_id' => $mobiles->id, 'name' => 'Smartphones', 'icon' => 'smartphone', 'sort_order' => 1, 'is_active' => true],
        );
        $tablets = Category::query()->updateOrCreate(
            ['slug' => 'tablets'],
            ['parent_id' => $mobiles->id, 'name' => 'Tablets', 'icon' => 'tablet_mac', 'sort_order' => 2, 'is_active' => true],
        );
        Category::query()
            ->whereNotIn('slug', array_merge(array_column($categoryDefinitions, 1), ['smartphones', 'tablets']))
            ->update(['is_active' => false]);
        Product::query()->where('slug', 'galaxy-s26-ultra')->update(['category_id' => $smartphones->id]);

        $products = [
            ['Galaxy A57', 'galaxy-a57', 'Samsung', 579, 'Ocean Blue', '#2563eb', 'Best seller', 'Balanced performance, a vivid display, and all-day battery life in a polished modern design.'],
            ['Galaxy S26+', 'galaxy-s26-plus', 'Samsung', 899, 'Orchid Pink', '#c084fc', 'New', 'Flagship performance, an advanced camera system, and a premium display for work and entertainment.'],
            ['Nova X Pro', 'nova-x-pro', 'Nova', 749, 'Champagne Gold', '#eab308', 'Popular', 'A premium metal design with fast performance, crisp photography, and dependable battery life.'],
            ['Pixel Air 10', 'pixel-air-10', 'Pixel', 649, 'Ocean Blue', '#2563eb', 'Top rated', 'A clean mobile experience with intelligent photography and smooth everyday performance.'],
            ['Tab Studio 12', 'tab-studio-12', 'Studio', 829, 'Champagne Gold', '#eab308', 'Creator pick', 'A spacious creator-focused tablet with a sharp display and flexible performance.'],
            ['Galaxy Tab Lite', 'galaxy-tab-lite', 'Samsung', 399, 'Ocean Blue', '#2563eb', 'Value', 'An accessible tablet for streaming, browsing, learning, and everyday family use.'],
            ['OneMax Ultra', 'onemax-ultra', 'OneMax', 999, 'Orchid Pink', '#c084fc', 'Premium', 'Top-tier power, a brilliant display, and a versatile camera in a refined flagship body.'],
            ['Fold Vision', 'fold-vision', 'Vision', 1299, 'Champagne Gold', '#eab308', 'Flagship', 'A foldable flagship that combines a compact form with an expansive productivity display.'],
        ];

        foreach ($products as $index => $definition) {
            $this->seedProduct(str_contains($definition[0], 'Tab') ? $tablets : $smartphones, $definition, $index);
        }
    }

    private function seedProduct(Category $category, array $definition, int $index): void
    {
        [$name, $slug, $brand, $basePrice, $primaryColorName, $primaryHex, $tag, $description] = $definition;

        $product = Product::withTrashed()->updateOrCreate(['slug' => $slug], [
            'category_id' => $category->id,
            'name' => $name,
            'short_description' => $description,
            'description' => $description,
            'specifications' => $this->specificationsFor($slug),
            'brand' => $brand,
            'status' => ProductStatus::Active,
            'is_featured' => true,
            'is_trending' => in_array($slug, ['galaxy-s26-plus', 'pixel-air-10', 'onemax-ultra', 'fold-vision'], true),
            'published_at' => now()->subMinutes($index),
            'offer_ends_at' => in_array($slug, ['galaxy-a57', 'galaxy-tab-lite'], true) ? now()->addDays(7) : null,
            'deleted_at' => null,
        ]);
        if ($product->trashed()) {
            $product->restore();
        }

        $storageOption = ProductOption::query()->updateOrCreate(
            ['product_id' => $product->id, 'slug' => ProductOption::STORAGE_SLUG],
            ['name' => 'Storage', 'sort_order' => 1, 'is_active' => true],
        );
        $colorOption = ProductOption::query()->updateOrCreate(
            ['product_id' => $product->id, 'slug' => ProductOption::COLOR_SLUG],
            ['name' => 'Color', 'sort_order' => 2, 'is_active' => true],
        );

        $storages = collect([
            ['256 GB', 0],
            ['512 GB', 100],
            ['1 TB', 250],
        ])->map(function (array $storage, int $sortOrder) use ($storageOption) {
            [$name, $priceAdjustment] = $storage;
            $value = ProductOptionValue::query()->updateOrCreate(
                ['product_option_id' => $storageOption->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'display_name' => $name, 'sort_order' => $sortOrder + 1, 'is_active' => true],
            );
            $value->setAttribute('price_adjustment', $priceAdjustment);

            return $value;
        });

        $colorDefinitions = collect([
            [$primaryColorName, $primaryHex],
            ['Graphite', '#1e293b'],
            ['Silver', '#cbd5e1'],
        ])->unique(fn (array $color) => $color[0])->values();
        $colors = $colorDefinitions->map(fn (array $color, int $sortOrder) => ProductOptionValue::query()->updateOrCreate(
            ['product_option_id' => $colorOption->id, 'slug' => Str::slug($color[0])],
            ['name' => $color[0], 'display_name' => $color[0], 'hex_value' => $color[1], 'sort_order' => $sortOrder + 1, 'is_active' => true],
        ));

        foreach ($storages as $storageIndex => $storage) {
            foreach ($colors as $colorIndex => $color) {
                $optionValueIds = [$storage->id, $color->id];
                $price = $basePrice + (int) $storage->getAttribute('price_adjustment');
                $signature = ProductVariant::buildOptionSignature($optionValueIds);
                $variant = ProductVariant::query()->updateOrCreate(
                    ['product_id' => $product->id, 'option_signature' => $signature],
                    [
                        'sku' => strtoupper(Str::slug($slug.'-'.$storageIndex.'-'.$colorIndex, '-')),
                        'price' => $price,
                        'compare_at_price' => $price + 80,
                        'stock_quantity' => 12 - $colorIndex,
                        'is_active' => true,
                    ],
                );
                $variant->optionValues()->sync($optionValueIds);
                $this->seedPlans($product, $variant, $price);
            }
        }
    }

    private function seedPlans(Product $product, ProductVariant $variant, float $price): void
    {
        $service = app(InstallmentPlanService::class);

        foreach ([3, 6, 9] as $payments) {
            $calculated = $service->calculatePlan($price, $payments, round($price / $payments, 2));
            InstallmentPlan::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'number_of_payments' => $payments,
                    'interval_type' => 'monthly',
                ],
                [
                    'down_payment' => $calculated['down_payment'],
                    'financing_fee' => 0,
                    'installment_amount' => $calculated['installment_amount'],
                    'total_amount' => $calculated['total_amount'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function specificationsFor(string $slug): array
    {
        $specifications = [
            'galaxy-a57' => ['Display' => '6.7-inch Super AMOLED', 'Processor' => 'Exynos 1580', 'Camera' => '50 MP triple camera', 'Battery' => '5,000 mAh', 'Connectivity' => '5G', 'SIM' => 'Dual SIM', 'Warranty' => '1 year'],
            'galaxy-s26-plus' => ['Display' => '6.7-inch Dynamic AMOLED 2X', 'Processor' => 'Snapdragon 8 Elite', 'Camera' => '50 MP pro camera system', 'Battery' => '4,900 mAh', 'Connectivity' => '5G', 'SIM' => 'Dual SIM', 'Warranty' => '1 year'],
            'nova-x-pro' => ['Display' => '6.8-inch OLED', 'Processor' => 'Nova X1 Pro', 'Camera' => '108 MP ultra-clear camera', 'Battery' => '5,200 mAh', 'Connectivity' => '5G', 'SIM' => 'Dual SIM', 'Warranty' => '1 year'],
            'pixel-air-10' => ['Display' => '6.3-inch Actua OLED', 'Processor' => 'Tensor G5', 'Camera' => '50 MP AI camera', 'Battery' => '4,700 mAh', 'Connectivity' => '5G', 'SIM' => 'Dual SIM', 'Warranty' => '1 year'],
            'tab-studio-12' => ['Display' => '12.4-inch 3K OLED', 'Processor' => 'Studio M3', 'Camera' => '13 MP rear camera', 'Battery' => '10,000 mAh', 'Connectivity' => 'Wi-Fi 6E', 'SIM' => 'Wi-Fi', 'Warranty' => '1 year'],
            'galaxy-tab-lite' => ['Display' => '11-inch LCD', 'Processor' => 'Exynos 1380', 'Camera' => '8 MP rear camera', 'Battery' => '8,000 mAh', 'Connectivity' => 'Wi-Fi 6', 'SIM' => 'Wi-Fi', 'Warranty' => '1 year'],
            'onemax-ultra' => ['Display' => '6.82-inch LTPO AMOLED', 'Processor' => 'Snapdragon 8 Elite', 'Camera' => '50 MP Hasselblad camera', 'Battery' => '6,000 mAh', 'Connectivity' => '5G', 'SIM' => 'Dual SIM', 'Warranty' => '1 year'],
            'fold-vision' => ['Display' => '7.6-inch foldable OLED', 'Processor' => 'Snapdragon 8 Elite', 'Camera' => '50 MP flexible camera', 'Battery' => '4,800 mAh', 'Connectivity' => '5G', 'SIM' => 'Dual SIM', 'Warranty' => '1 year'],
        ];

        return $specifications[$slug] ?? ['Connectivity' => '5G', 'SIM' => 'Dual SIM', 'Warranty' => '1 year'];
    }
}
