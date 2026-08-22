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

    public function test_home_shows_only_featured_available_products_as_recommendations(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $recommended = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Recommended phone',
            'status' => ProductStatus::Active,
            'is_featured' => true,
        ]);
        $notRecommended = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Regular phone',
            'status' => ProductStatus::Active,
            'is_featured' => false,
        ]);
        ProductVariant::factory()->create(['product_id' => $recommended->id, 'is_active' => true, 'stock_quantity' => 1]);
        ProductVariant::factory()->create(['product_id' => $notRecommended->id, 'is_active' => true, 'stock_quantity' => 1]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Recommended products')
            ->assertSeeText($recommended->name)
            ->assertDontSeeText($notRecommended->name);
    }

    public function test_home_shows_only_active_discounted_limited_time_offers(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $offer = Product::factory()->create(['category_id' => $category->id, 'name' => 'Flash sale phone', 'offer_ends_at' => now()->addDay()]);
        $expired = Product::factory()->create(['category_id' => $category->id, 'name' => 'Expired sale phone', 'offer_ends_at' => now()->subDay()]);
        ProductVariant::factory()->create(['product_id' => $offer->id, 'price' => 700, 'compare_at_price' => 900, 'stock_quantity' => 1]);
        ProductVariant::factory()->create(['product_id' => $expired->id, 'price' => 700, 'compare_at_price' => 900, 'stock_quantity' => 1]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Limited-time offers')
            ->assertSeeText($offer->name)
            ->assertDontSeeText($expired->name);
    }

    public function test_home_shows_only_trending_available_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $trending = Product::factory()->create(['category_id' => $category->id, 'name' => 'Trending phone', 'is_trending' => true]);
        $regular = Product::factory()->create(['category_id' => $category->id, 'name' => 'Regular phone', 'is_trending' => false]);
        ProductVariant::factory()->create(['product_id' => $trending->id, 'stock_quantity' => 1]);
        ProductVariant::factory()->create(['product_id' => $regular->id, 'stock_quantity' => 1]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Trending products')
            ->assertSeeText($trending->name)
            ->assertDontSeeText($regular->name);
    }

    public function test_home_shows_a_low_stock_alert_for_recommended_products(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_featured' => true]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 2]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Only 2 left');
    }
}
