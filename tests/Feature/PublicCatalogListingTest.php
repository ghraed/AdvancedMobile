<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_search_results_explain_how_to_recover(): void
    {
        $this->get(route('search', ['q' => 'not-a-product']))
            ->assertOk()->assertSeeText('No products match these filters')->assertSeeText('Clear filters');
    }

    public function test_catalog_filters_sorts_and_keeps_query_string_on_pagination(): void
    {
        $category = Category::factory()->create(['name' => 'Phones']);
        $cheap = $this->product($category, 'Affordable Phone', 'Acme', 300);
        $expensive = $this->product($category, 'Premium Phone', 'Acme', 900);
        $this->storage($cheap, '128 GB');
        $this->storage($expensive, '256 GB');

        $response = $this->get('/catalog?brand=Acme&storage=128%20GB&sort=price_desc');

        $response->assertOk()->assertSeeText('Affordable Phone')->assertDontSeeText('Premium Phone');
    }

    public function test_pagination_preserves_selected_filters(): void
    {
        $category = Category::factory()->create();
        foreach (range(1, 19) as $number) {
            $this->product($category, 'Paged '.$number, 'Acme', 100 + $number);
        }

        $this->get('/catalog?brand=Acme&page=2')->assertOk()->assertSee('brand=Acme', false)->assertSeeText('Paged 1');
    }

    public function test_catalog_hides_unavailable_products_and_option_values(): void
    {
        $category = Category::factory()->create();
        $visible = $this->product($category, 'Visible', null, 500);
        $this->storage($visible, '128 GB');
        $hidden = Product::factory()->create(['category_id' => $category->id, 'name' => 'Hidden', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $hidden->id, 'is_active' => false, 'stock_quantity' => 5]);

        $this->get('/catalog')->assertOk()->assertSeeText('Visible')->assertDontSeeText('Hidden')->assertSeeText('128 GB');
    }

    public function test_category_includes_available_descendant_products_and_shows_children(): void
    {
        $parent = Category::factory()->create(['name' => 'Phones']);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Android']);
        $this->product($child, 'Child phone', null, 400);

        $this->get(route('categories.show', $parent))->assertOk()->assertSeeText('Android')->assertSeeText('Child phone');
    }

    public function test_lowest_valid_installment_is_rendered_and_can_be_filtered(): void
    {
        $category = Category::factory()->create();
        $product = $this->product($category, 'Installment phone', null, 600);
        InstallmentPlan::factory()->create(['product_id' => $product->id, 'number_of_payments' => 6, 'installment_amount' => 100, 'is_active' => true]);
        InstallmentPlan::factory()->create(['product_id' => $product->id, 'number_of_payments' => 3, 'installment_amount' => 250, 'is_active' => false]);

        $this->get('/catalog?payments=6')->assertOk()->assertSeeText('From 100.00 × 6');
        $this->get('/catalog?payments=3')->assertOk()->assertDontSeeText('Installment phone');
    }

    private function product(Category $category, string $name, ?string $brand, int $price): Product
    {
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => $name, 'brand' => $brand, 'status' => ProductStatus::Active, 'published_at' => now()->addSeconds($price)]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => $price, 'stock_quantity' => 2, 'is_active' => true]);

        return $product;
    }

    private function storage(Product $product, string $name): void
    {
        $option = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Storage', 'slug' => 'storage']);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'name' => $name, 'display_name' => $name]);
        $product->variants()->first()->optionValues()->attach($value);
    }
}
