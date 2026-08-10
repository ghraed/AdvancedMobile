<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CategoryMenuService
{
    public function visibleRootCategories(): Collection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->ordered()
            ->get(['id', 'parent_id', 'name', 'slug', 'description', 'icon', 'image', 'sort_order', 'is_active']);

        // Only expose categories containing an available product, plus the
        // active ancestors needed to navigate to those products.
        $visibleIds = $this->resolveVisibleCategoryIds($categories);

        return $this->buildVisibleTree($categories, null, $visibleIds);
    }

    public function resolveVisibleCategoryIds(Collection $categories): array
    {
        return $this->resolveVisibleCategoryIdsFromDirectVisibility(
            $categories,
            $this->directlyVisibleCategoryIds($categories->pluck('id')->all()),
        );
    }

    public function resolveMenuStateMap(Collection $categories): array
    {
        $directlyVisibleIds = $this->directlyVisibleCategoryIds($categories->pluck('id')->all());
        $directlyVisibleLookup = array_fill_keys($directlyVisibleIds, true);
        $childrenByParent = $categories->groupBy('parent_id');
        $stateMap = [];

        foreach ($categories as $category) {
            $this->resolveMenuStateForCategory($category, $childrenByParent, $directlyVisibleLookup, $stateMap);
        }

        return collect($stateMap)
            ->map(fn (array $state) => $state['status'])
            ->all();
    }

    public function directlyVisibleCategoryIds(array $categoryIds = []): array
    {
        return Category::query()
            ->select('categories.id')
            ->join('products', function ($join): void {
                $join->on('products.category_id', '=', 'categories.id')
                    ->whereNull('products.deleted_at')
                    ->where('products.status', 'active');
            })
            ->join('product_variants', function ($join): void {
                $join->on('product_variants.product_id', '=', 'products.id')
                    ->where('product_variants.is_active', true)
                    ->where('product_variants.stock_quantity', '>', 0);
            })
            ->where('categories.is_active', true)
            ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('categories.id', $categoryIds))
            ->distinct()
            ->pluck('categories.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function resolveVisibleCategoryIdsFromDirectVisibility(Collection $categories, array $directlyVisibleIds): array
    {
        $visibleLookup = array_fill_keys($directlyVisibleIds, true);
        $byId = $categories->keyBy('id');

        foreach ($directlyVisibleIds as $categoryId) {
            $current = $byId->get($categoryId);

            while ($current && $current->parent_id !== null) {
                $parent = $byId->get($current->parent_id);

                if (! $parent || ! $parent->is_active || isset($visibleLookup[$parent->id])) {
                    break;
                }

                $visibleLookup[$parent->id] = true;
                $current = $parent;
            }
        }

        return array_map('intval', array_keys($visibleLookup));
    }

    protected function buildVisibleTree(Collection $categories, ?int $parentId, array $visibleIds): Collection
    {
        $visibleLookup = array_fill_keys($visibleIds, true);
        $children = $categories
            ->filter(function (Category $category) use ($categories, $parentId, $visibleLookup): bool {
                if (! isset($visibleLookup[$category->id])) {
                    return false;
                }

                if ($parentId === null) {
                    return $category->parent_id === null || ! $categories->pluck('id')->contains($category->parent_id);
                }

                return $category->parent_id === $parentId;
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return $children->map(function (Category $category) use ($categories, $visibleIds) {
            $category->setRelation('childrenRecursive', $this->buildVisibleTree($categories, $category->id, $visibleIds));

            return $category;
        });
    }

    protected function resolveMenuStateForCategory(
        Category $category,
        Collection $childrenByParent,
        array $directlyVisibleLookup,
        array &$stateMap,
    ): array {
        if (isset($stateMap[$category->id])) {
            return $stateMap[$category->id];
        }

        $children = $childrenByParent->get($category->id, collect());
        $resolvedChildren = $children->map(
            fn (Category $child) => $this->resolveMenuStateForCategory($child, $childrenByParent, $directlyVisibleLookup, $stateMap)
        );

        $isDirectlyVisible = isset($directlyVisibleLookup[$category->id]);
        $hasVisibleDescendants = $resolvedChildren->contains(fn (array $state) => $state['is_visible']);
        $subtreeProductCount = $resolvedChildren->sum('subtree_product_count') + (int) ($isDirectlyVisible ? 1 : 0);
        $isVisible = $category->is_active && ($isDirectlyVisible || $hasVisibleDescendants);

        return $stateMap[$category->id] = [
            'is_visible' => $isVisible,
            'status' => match (true) {
                ! $category->is_active => 'inactive',
                $isVisible => 'visible',
                $subtreeProductCount === 0 => 'empty',
                default => 'unavailable',
            },
            'subtree_product_count' => $subtreeProductCount,
        ];
    }
}
