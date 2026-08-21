<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Services\InstallmentCalculatorService;
use App\Services\InstallmentPlanService;
use App\Services\ProductCatalogService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected ProductCatalogService $productCatalogService,
        protected InstallmentPlanService $installmentPlanService,
        protected InstallmentCalculatorService $installmentCalculatorService,
    ) {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'category_id' => $request->filled('category_id') ? (int) $request->integer('category_id') : null,
            'status' => (string) $request->string('status', 'all'),
            'stock' => (string) $request->string('stock', 'all'),
            'installments' => (string) $request->string('installments', 'all'),
            'sort' => (string) $request->string('sort', 'date'),
            'direction' => (string) $request->string('direction', 'desc'),
        ];

        $totalStockSubquery = ProductVariant::query()
            ->selectRaw('COALESCE(SUM(stock_quantity), 0)')
            ->whereColumn('product_id', 'products.id');

        $products = Product::query()
            ->select('products.*')
            ->with([
                'category:id,name,is_active',
                'images' => fn ($query) => $query->whereNull('product_option_value_id')->select('id', 'product_id', 'product_option_value_id', 'product_variant_id', 'image_path', 'is_primary', 'sort_order'),
            ])
            ->withCount(['variants', 'installmentPlans'])
            ->withMin('variants as min_variant_price', 'price')
            ->withMax('variants as max_variant_price', 'price')
            ->withSum('variants as total_stock', 'stock_quantity')
            ->withExists([
                'variants as has_available_variants' => fn (Builder $query) => $query->available(),
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $query->where(function (Builder $nested) use ($filters) {
                    $nested
                        ->where('products.name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('products.brand', 'like', '%'.$filters['search'].'%')
                        ->orWhereHas('variants', fn (Builder $variantQuery) => $variantQuery->where('sku', 'like', '%'.$filters['search'].'%'));
                });
            })
            ->when($filters['category_id'], fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('products.status', $filters['status']))
            ->when($filters['installments'] === 'with', fn (Builder $query) => $query->has('installmentPlans'))
            ->when($filters['installments'] === 'without', fn (Builder $query) => $query->doesntHave('installmentPlans'))
            ->when($filters['stock'] === 'in_stock', fn (Builder $query) => $query->whereRaw('('.$totalStockSubquery->toSql().') > 5', $totalStockSubquery->getBindings()))
            ->when($filters['stock'] === 'low_stock', fn (Builder $query) => $query->whereRaw('('.$totalStockSubquery->toSql().') BETWEEN 1 AND 5', $totalStockSubquery->getBindings()))
            ->when($filters['stock'] === 'out_of_stock', fn (Builder $query) => $query->whereRaw('('.$totalStockSubquery->toSql().') = 0', $totalStockSubquery->getBindings()));

        $direction = $filters['direction'] === 'asc' ? 'asc' : 'desc';

        match ($filters['sort']) {
            'name' => $products->orderBy('products.name', $direction),
            'minimum_price' => $products->orderBy('min_variant_price', $direction)->orderBy('products.name'),
            'stock' => $products->orderBy('total_stock', $direction)->orderBy('products.name'),
            'category' => $products->orderBy(
                Category::query()->select('name')->whereColumn('categories.id', 'products.category_id'),
                $direction
            )->orderBy('products.name'),
            default => $products->orderBy('products.created_at', $direction)->orderBy('products.id', $direction),
        };

        return view('admin.products.index', [
            'products' => $products->paginate(15)->withQueryString(),
            'filters' => $filters,
            'categories' => Category::query()->ordered()->get(['id', 'parent_id', 'name', 'is_active']),
            'sortOptions' => [
                'date' => 'Date',
                'name' => 'Name',
                'minimum_price' => 'Minimum price',
                'stock' => 'Stock',
                'category' => 'Category',
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', $this->formData(new Product));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->productCatalogService->save(new Product, $request->validated());
        $request->session()->forget('product_form_draft_id');

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load([
            'category',
            'productOptions.values',
            'installmentPlans',
            'images.optionValue',
            'variants.images',
            'variants.optionValues.productOption',
        ]);

        return view('admin.products.edit', $this->formData($product));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->productCatalogService->save($product, $request->validated());
        $request->session()->forget('product_form_draft_id');

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('status', 'Product updated.')
            ->with('warnings', $this->productCatalogService->variantRetirementWarnings());
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $copy = $this->productCatalogService->duplicate($product);

        return redirect()->route('admin.products.edit', $copy)->with('status', 'Product duplicated.');
    }

    public function activate(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->update([
            'status' => ProductStatus::Active,
            'published_at' => $product->published_at ?? Carbon::now(),
        ]);

        return redirect()->back()->with('status', 'Product activated.');
    }

    public function deactivate(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $product->update(['status' => ProductStatus::Draft]);

        return redirect()->back()->with('status', 'Product deactivated.');
    }

    public function preview(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load([
            'category',
            'images.optionValue',
            'variants.images',
            'variants.optionValues.productOption',
            'installmentPlans',
        ]);

        return view('elite-mobile-marketplace.product-details', [
            'activeTab' => 'shop',
            'productModel' => $product,
            'isPreview' => true,
        ]);
    }

    public function previewInstallmentPlan(Request $request, ?Product $product = null): JsonResponse
    {
        $product ? $this->authorize('update', $product) : $this->authorize('create', Product::class);

        $validated = $request->validate([
            'scope' => ['required', 'in:product,variant'],
            'variant_key' => ['nullable', 'string'],
            'product_variant_id' => ['nullable', 'integer'],
            'number_of_payments' => ['required', 'integer', 'in:3,6,9'],
            'total_amount' => ['required', 'numeric', 'gt:0'],
            'interval_type' => ['required', 'in:'.implode(',', $this->installmentCalculatorService->intervalTypes())],
            'variants' => ['nullable', 'array'],
            'variants.*.client_key' => ['nullable', 'string'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['nullable', 'string'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $price = (float) $validated['total_amount'];
            $preview = $this->installmentPlanService->previewFromPayload($validated, $price);

            return response()->json([
                'price' => round($price, 2),
                'preview' => $preview,
            ]);
        } catch (DomainException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->variants()->where('stock_quantity', '>', 0)->exists()) {
            return redirect()
                ->route('admin.products.edit', $product)
                ->withErrors([
                    'delete' => 'Products with remaining stock cannot be deleted. Set stock to zero or retire the variants first.',
                ]);
        }

        $this->productCatalogService->delete($product);

        return redirect()->route('admin.products.index')->with('status', 'Product and its catalog data deleted permanently.');
    }

    protected function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::query()->ordered()->get(['id', 'parent_id', 'name', 'is_active']),
            'categoryOptions' => $this->flattenCategoryOptions(Category::query()->ordered()->get()),
            'optionDefinitions' => [
                ['name' => 'Storage', 'slug' => ProductOption::STORAGE_SLUG, 'supports_hex' => false, 'supports_swatch' => false],
                ['name' => 'Color', 'slug' => ProductOption::COLOR_SLUG, 'supports_hex' => true, 'supports_swatch' => true],
            ],
            'installmentIntervalOptions' => collect($this->installmentCalculatorService->intervalTypes())
                ->mapWithKeys(fn (string $type) => [$type => str($type)->headline()])
                ->all(),
            'variantStockSummary' => [
                'total' => $product->exists ? $product->variants()->count() : 0,
                'low_stock' => $product->exists ? $product->variants()->whereBetween('stock_quantity', [1, 5])->count() : 0,
                'out_of_stock' => $product->exists ? $product->variants()->where('stock_quantity', 0)->count() : 0,
                'plans' => $product->exists ? $product->installmentPlans()->count() : 0,
            ],
        ];
    }

    protected function flattenCategoryOptions($categories, ?int $parentId = null, int $depth = 0): array
    {
        $children = $categories
            ->filter(fn (Category $category) => $parentId === null ? $category->parent_id === null : $category->parent_id === $parentId)
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->values();

        $flattened = [];

        foreach ($children as $category) {
            $hasChildren = $categories->contains(fn (Category $candidate) => $candidate->parent_id === $category->id);

            $flattened[] = [
                'id' => $category->id,
                'label' => str_repeat('— ', $depth).$category->name.($category->is_active ? '' : ' [Inactive]'),
                'is_leaf' => ! $hasChildren,
                'is_active' => $category->is_active,
            ];

            $flattened = [...$flattened, ...$this->flattenCategoryOptions($categories, $category->id, $depth + 1)];
        }

        return $flattened;
    }

    protected function previewVariantPrice(array $validated, ?Product $product = null): float
    {
        if (($validated['scope'] ?? 'product') === 'variant') {
            $variantId = $validated['product_variant_id'] ?? null;

            if ($variantId !== null && $product !== null) {
                $variant = $product->variants()->findOrFail($variantId);

                return (float) $variant->price;
            }

            $matchedVariant = collect($validated['variants'] ?? [])->first(function (array $variant) use ($validated) {
                $clientKey = (string) ($variant['client_key'] ?? $variant['id'] ?? '');

                return $clientKey !== '' && $clientKey === ($validated['variant_key'] ?? null);
            });

            if (! $matchedVariant) {
                throw new DomainException('Variant-specific previews require a matching variant price.');
            }

            return round((float) $matchedVariant['price'], 2);
        }

        $matchedVariant = collect($validated['variants'] ?? [])->first();

        if ($matchedVariant) {
            return round((float) $matchedVariant['price'], 2);
        }

        if ($product) {
            $variant = $product->variants()->orderBy('price')->first();

            if ($variant) {
                return (float) $variant->price;
            }
        }

        throw new DomainException('At least one variant price is required to preview a product-level installment plan.');
    }
}
