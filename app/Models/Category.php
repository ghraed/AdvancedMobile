<?php

namespace App\Models;

use App\Enums\ProductStatus;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }

            if ($category->parent_id !== null && $category->parent_id === $category->id) {
                throw new DomainException('A category cannot be its own parent.');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('name');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisibleInMenu(Builder $query): Builder
    {
        $categories = (clone $query)
            ->ordered()
            ->get();

        $directlyVisibleIds = self::query()
            ->select('categories.id')
            ->join('products', function ($join): void {
                $join->on('products.category_id', '=', 'categories.id')
                    ->whereNull('products.deleted_at')
                    ->where('products.status', ProductStatus::Active->value);
            })
            ->join('product_variants', function ($join): void {
                $join->on('product_variants.product_id', '=', 'products.id')
                    ->where('product_variants.is_active', true)
                    ->where('product_variants.stock_quantity', '>', 0);
            })
            ->where('categories.is_active', true)
            ->distinct()
            ->pluck('categories.id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $visibleIds = self::resolveVisibleIdsFromAncestors($categories, $directlyVisibleIds);
        $orderedIds = self::orderedHierarchyIds($categories, $visibleIds);

        if ($orderedIds === []) {
            return $query->whereRaw('1 = 0');
        }

        $grammar = $query->getQuery()->getGrammar();
        $table = $this->getTable();
        $cases = collect($orderedIds)
            ->values()
            ->map(fn (int $id, int $index) => 'WHEN '.$id.' THEN '.$index)
            ->implode(' ');

        return $query
            ->whereKey($orderedIds)
            ->orderByRaw('CASE '.$grammar->wrap("{$table}.id").' '.$cases.' END');
    }

    public static function resolveVisibleInMenuIds(Collection $categories): array
    {
        $childrenByParent = $categories->groupBy('parent_id');
        $visibleIds = [];

        foreach ($categories as $category) {
            if (self::hasVisibleInventory($category, $childrenByParent, $visibleIds)) {
                $visibleIds[] = $category->id;
            }
        }

        return array_values(array_unique($visibleIds));
    }

    public static function resolveVisibleIdsFromAncestors(Collection $categories, array $directlyVisibleIds): array
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

    public static function orderedHierarchyIds(Collection $categories, array $visibleIds): array
    {
        $visibleLookup = array_fill_keys($visibleIds, true);
        $ordered = [];

        $walk = function (?int $parentId, int $depth = 0) use (&$walk, $categories, $visibleLookup, &$ordered): void {
            $categoryIds = $categories->pluck('id')->all();
            $children = ($parentId === null
                ? $categories->filter(fn (self $category) => $category->parent_id === null || ! in_array($category->parent_id, $categoryIds, true))
                : $categories->where('parent_id', $parentId))
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['name', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            foreach ($children as $category) {
                if (isset($visibleLookup[$category->id])) {
                    $ordered[] = (int) $category->id;
                }

                $walk($category->id, $depth + 1);
            }
        };

        $walk(null);

        return $ordered;
    }

    public static function resolveMenuStateMap(Collection $categories): array
    {
        $childrenByParent = $categories->groupBy('parent_id');
        $stateMap = [];
        $memo = [];

        foreach ($categories as $category) {
            $resolved = self::resolveMenuStateForCategory($category, $childrenByParent, $memo);

            $stateMap[$category->id] = $resolved['status'];
        }

        return $stateMap;
    }

    public function descendantIds(): array
    {
        $descendantIds = [];
        $pendingParentIds = [$this->id];

        while ($pendingParentIds !== []) {
            $children = self::query()
                ->whereIn('parent_id', $pendingParentIds)
                ->pluck('id')
                ->all();

            if ($children === []) {
                break;
            }

            $descendantIds = array_merge($descendantIds, $children);
            $pendingParentIds = $children;
        }

        return array_values(array_unique($descendantIds));
    }

    protected static function hasVisibleInventory(self $category, Collection $childrenByParent, array &$visibleIds): bool
    {
        $hasDirectVisibleProducts = $category->is_active && $category->products->contains(
            fn (Product $product) => $product->status->value === ProductStatus::Active->value
                && $product->variants->contains(fn (ProductVariant $variant) => $variant->is_active && $variant->stock_quantity > 0)
        );

        $hasVisibleChildren = $childrenByParent->get($category->id, collect())
            ->contains(function (self $child) use ($childrenByParent, &$visibleIds) {
                $isVisible = self::hasVisibleInventory($child, $childrenByParent, $visibleIds);

                if ($isVisible) {
                    $visibleIds[] = $child->id;
                }

                return $isVisible;
            });

        return $category->is_active && ($hasDirectVisibleProducts || $hasVisibleChildren);
    }

    protected static function resolveMenuStateForCategory(self $category, Collection $childrenByParent, array &$memo): array
    {
        if (array_key_exists($category->id, $memo)) {
            return $memo[$category->id];
        }

        $directProductCount = $category->relationLoaded('products') ? $category->products->count() : 0;
        $hasDirectVisibleProducts = $category->is_active && $category->relationLoaded('products') && $category->products->contains(
            fn (Product $product) => $product->status === ProductStatus::Active
                && $product->variants->contains(fn (ProductVariant $variant) => $variant->is_active && $variant->stock_quantity > 0)
        );

        $descendantStates = $childrenByParent->get($category->id, collect())
            ->map(fn (self $child) => self::resolveMenuStateForCategory($child, $childrenByParent, $memo));

        $subtreeProductCount = $directProductCount + $descendantStates->sum('subtree_product_count');
        $hasVisibleDescendants = $descendantStates->contains(fn (array $state) => $state['is_visible']);
        $isVisible = $category->is_active && ($hasDirectVisibleProducts || $hasVisibleDescendants);

        $status = match (true) {
            ! $category->is_active => 'inactive',
            $isVisible => 'visible',
            $subtreeProductCount === 0 => 'empty',
            default => 'unavailable',
        };

        return $memo[$category->id] = [
            'is_visible' => $isVisible,
            'status' => $status,
            'subtree_product_count' => $subtreeProductCount,
        ];
    }
}
