<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_categories_never_appear_in_the_visitor_drawer(): void
    {
        $empty = Category::factory()->create(['name' => 'Empty category', 'is_active' => true]);
        $hidden = Category::factory()->create(['name' => 'Out of stock category', 'is_active' => true]);
        $product = Product::factory()->create(['category_id' => $hidden->id, 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'stock_quantity' => 0]);

        $this->get('/')
            ->assertOk()
            ->assertDontSeeText($empty->name)
            ->assertDontSeeText($hidden->name);
    }

    public function test_parent_category_appears_when_only_a_child_has_an_available_product(): void
    {
        $parent = Category::factory()->create(['name' => 'Parent category', 'sort_order' => 1, 'is_active' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Available child', 'is_active' => true]);
        $product = Product::factory()->create(['category_id' => $child->id, 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true, 'stock_quantity' => 1]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText($parent->name)
            ->assertSeeText($child->name);
    }
}
