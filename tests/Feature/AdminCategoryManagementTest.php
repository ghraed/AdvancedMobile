<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_view_and_edit_a_category(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Phones']);

        $createResponse = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Android Phones',
            'slug' => 'Android Phones',
            'parent_id' => $parent->id,
            'description' => 'Catalog bucket',
            'sort_order' => 4,
            'is_active' => 1,
            'icon' => UploadedFile::fake()->image('icon.png'),
            'image' => UploadedFile::fake()->image('image.png'),
        ]);

        $category = Category::query()->where('name', 'Android Phones')->firstOrFail();

        $createResponse->assertRedirect(route('admin.categories.show', $category));
        Storage::disk('public')->assertExists($category->icon);
        Storage::disk('public')->assertExists($category->image);

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSeeText('Android Phones')
            ->assertSeeText('Hidden because empty');

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Android',
                'slug' => 'android',
                'parent_id' => '',
                'description' => 'Updated',
                'sort_order' => 2,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.categories.show', $category));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Android',
            'parent_id' => null,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_activate_deactivate_and_reorder_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $first = Category::factory()->create(['sort_order' => 1, 'is_active' => true]);
        $second = Category::factory()->create(['sort_order' => 2, 'is_active' => false]);

        $this->actingAs($admin)
            ->patch(route('admin.categories.deactivate', $first))
            ->assertRedirect();

        $this->actingAs($admin)
            ->patch(route('admin.categories.activate', $second))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.categories.reorder'), [
                'sort_orders' => [
                    $first->id => 9,
                    $second->id => 3,
                ],
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['id' => $first->id, 'is_active' => false, 'sort_order' => 9]);
        $this->assertDatabaseHas('categories', ['id' => $second->id, 'is_active' => true, 'sort_order' => 3]);
    }

    public function test_admin_must_reassign_products_before_deleting_a_category(): void
    {
        $admin = User::factory()->admin()->create();
        $source = Category::factory()->create();
        $target = Category::factory()->create();
        Product::factory()->create(['category_id' => $source->id]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $source))
            ->assertRedirect(route('admin.categories.show', $source))
            ->assertSessionHasErrors('delete');

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $source), [
                'reassign_products_to' => $target->id,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $source->id]);
        $this->assertDatabaseHas('products', ['category_id' => $target->id]);
    }

    public function test_admin_cannot_create_circular_parent_relationships(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $parent), [
                'name' => $parent->name,
                'slug' => $parent->slug,
                'parent_id' => $child->id,
                'description' => $parent->description,
                'sort_order' => $parent->sort_order,
                'is_active' => 1,
            ])
            ->assertSessionHasErrors('parent_id');
    }
}
