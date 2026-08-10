<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryDeleteRequest;
use App\Http\Requests\CategoryReorderRequest;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryMenuService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryMenuService $categoryMenuService,
    ) {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'status' => (string) $request->string('status', 'all'),
            'structure' => (string) $request->string('structure', 'all'),
            'sort' => (string) $request->string('sort', 'sort_order'),
            'direction' => (string) $request->string('direction', 'asc'),
            'page' => max(1, (int) $request->integer('page', 1)),
        ];

        $categories = Category::query()
            ->with('parent')
            ->withCount([
                'products',
                'products as active_products_count' => fn ($query) => $query->where('status', ProductStatus::Active),
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where('name', 'like', '%'.$filters['search'].'%');
            })
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['structure'] === 'parent', fn ($query) => $query->whereNull('parent_id'))
            ->when($filters['structure'] === 'subcategory', fn ($query) => $query->whereNotNull('parent_id'))
            ->get();

        $visibilityMap = $this->categoryMenuService->resolveMenuStateMap($categories);
        $sort = in_array($filters['sort'], ['name', 'sort_order', 'created_at', 'products_count'], true) ? $filters['sort'] : 'sort_order';
        $direction = $filters['direction'] === 'desc' ? 'desc' : 'asc';
        $hierarchical = $this->flattenHierarchy($categories, null, 0, $sort, $direction, $visibilityMap);
        $paginated = $this->paginateCollection($hierarchical, 15, $filters['page'], $request->url(), $request->query());

        return view('admin.categories.index', [
            'categories' => $paginated,
            'filters' => $filters,
            'sortOptions' => [
                'sort_order' => 'Sort order',
                'name' => 'Name',
                'created_at' => 'Creation date',
                'products_count' => 'Product count',
            ],
            'visibilityLabels' => $this->visibilityLabels(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', $this->formData(new Category));
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = new Category($request->safe()->except(['icon', 'image']));
        $this->fillUploads($category, $request);
        $category->save();

        return redirect()->route('admin.categories.show', $category)->with('status', 'Category created.');
    }

    public function show(Category $category): View
    {
        $category->load([
            'parent',
            'children' => fn ($query) => $query->withCount('products'),
        ])->loadCount([
            'products',
            'children',
            'products as active_products_count' => fn ($query) => $query->where('status', ProductStatus::Active),
        ]);

        $relatedCategories = Category::query()
            ->whereKey(array_merge([$category->id], $category->descendantIds()))
            ->get();

        $visibilityMap = $this->categoryMenuService->resolveMenuStateMap($relatedCategories);

        return view('admin.categories.show', [
            'category' => $category,
            'visibilityStatus' => $visibilityMap[$category->id] ?? 'empty',
            'visibilityLabels' => $this->visibilityLabels(),
            'reassignmentOptions' => $this->reassignmentOptions($category),
            'childVisibilityMap' => $visibilityMap,
        ]);
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', $this->formData($category));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->fill($request->safe()->except(['icon', 'image']));
        $this->fillUploads($category, $request);
        $category->save();

        return redirect()->route('admin.categories.show', $category)->with('status', 'Category updated.');
    }

    public function activate(Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update(['is_active' => true]);

        return redirect()->back()->with('status', 'Category activated.');
    }

    public function deactivate(Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update(['is_active' => false]);

        return redirect()->back()->with('status', 'Category deactivated.');
    }

    public function reorder(CategoryReorderRequest $request): RedirectResponse
    {
        $this->authorize('reorder', Category::class);

        DB::transaction(function () use ($request): void {
            foreach (($request->validated())['sort_orders'] as $categoryId => $sortOrder) {
                Category::query()->whereKey($categoryId)->update([
                    'sort_order' => (int) $sortOrder,
                ]);
            }
        });

        return redirect()->route('admin.categories.index', $request->query())->with('status', 'Category order updated.');
    }

    public function destroy(CategoryDeleteRequest $request, Category $category): RedirectResponse
    {
        if ($category->children()->exists()) {
            return redirect()->route('admin.categories.show', $category)->withErrors([
                'delete' => 'Delete or move child categories before deleting this category.',
            ]);
        }

        $reassignId = $request->integer('reassign_products_to');
        $hasProducts = $category->products()->exists();

        if ($hasProducts && ! $reassignId) {
            return redirect()->route('admin.categories.show', $category)->withErrors([
                'delete' => 'Move products to another category before deleting this category.',
            ]);
        }

        DB::transaction(function () use ($category, $reassignId, $hasProducts): void {
            if ($hasProducts && $reassignId) {
                Product::query()
                    ->where('category_id', $category->id)
                    ->update(['category_id' => $reassignId]);
            }

            $category->delete();
        });

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }

    protected function formData(Category $category): array
    {
        $allCategories = Category::query()->ordered()->get(['id', 'parent_id', 'name']);
        $forbiddenIds = $category->exists ? array_merge([$category->id], $category->descendantIds()) : [];

        return [
            'category' => $category,
            'parentCategories' => $this->buildParentOptions($allCategories, $forbiddenIds),
            'reassignmentOptions' => $category->exists ? $this->reassignmentOptions($category) : [],
        ];
    }

    protected function fillUploads(Category $category, CategoryRequest $request): void
    {
        if ($request->hasFile('icon')) {
            $this->deleteCategoryAsset($category->icon);
            $category->icon = $request->file('icon')->store('categories/icons', 'public');
        }

        if ($request->hasFile('image')) {
            $this->deleteCategoryAsset($category->image);
            $category->image = $request->file('image')->store('categories/images', 'public');
        }
    }

    protected function deleteCategoryAsset(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    protected function buildParentOptions(Collection $categories, array $forbiddenIds = []): array
    {
        return $this->flattenHierarchy($categories->whereNotIn('id', $forbiddenIds)->values())
            ->mapWithKeys(fn (Category $category) => [$category->id => str_repeat('— ', $category->depth).$category->name])
            ->all();
    }

    protected function flattenHierarchy(
        Collection $categories,
        ?int $parentId = null,
        int $depth = 0,
        string $sort = 'sort_order',
        string $direction = 'asc',
        array $visibilityMap = [],
    ): Collection {
        $categoryIds = $categories->pluck('id')->all();
        $children = ($parentId === null
            ? $categories->filter(fn (Category $category) => $category->parent_id === null || ! in_array($category->parent_id, $categoryIds, true))
            : $categories->where('parent_id', $parentId))
            ->sortBy([
                [$sort, $direction],
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $flattened = collect();

        foreach ($children as $category) {
            $category->depth = $depth;
            $category->menu_visibility_status = $visibilityMap[$category->id] ?? 'empty';
            $flattened->push($category);
            $flattened = $flattened->merge($this->flattenHierarchy($categories, $category->id, $depth + 1, $sort, $direction, $visibilityMap));
        }

        return $flattened;
    }

    protected function paginateCollection(Collection $items, int $perPage, int $page, string $path, array $query): LengthAwarePaginator
    {
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $results,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $path,
                'query' => $query,
            ],
        );
    }

    protected function visibilityLabels(): array
    {
        return [
            'visible' => 'Visible in menu',
            'empty' => 'Hidden because empty',
            'inactive' => 'Hidden because inactive',
            'unavailable' => 'Hidden because products are unavailable',
        ];
    }

    protected function reassignmentOptions(Category $category): array
    {
        $forbiddenIds = array_merge([$category->id], $category->descendantIds());

        return $this->buildParentOptions(
            Category::query()->ordered()->get(['id', 'parent_id', 'name'])->whereNotIn('id', $forbiddenIds)->values()
        );
    }
}
