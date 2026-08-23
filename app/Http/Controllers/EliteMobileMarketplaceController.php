<?php

namespace App\Http\Controllers;

use App\Enums\AccessorySubtype;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\DeviceUnit;
use App\Enums\DeviceConditionType;
use App\Enums\DeviceUnitStatus;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Services\CategoryMenuService;
use App\Services\InstallmentPlanService;
use App\Services\ProductImageResolver;
use App\Services\AccessoryCompatibilityService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class EliteMobileMarketplaceController extends Controller
{
    public function __construct(
        protected CategoryMenuService $categoryMenuService,
        protected InstallmentPlanService $installmentPlanService,
        protected ProductImageResolver $productImageResolver,
        protected AccessoryCompatibilityService $accessoryCompatibilityService,
    ) {}

    public function home(): View
    {
        $recommendedProducts = Product::query()->publiclyAvailable()->with($this->productRelations())
            ->where('is_featured', true)
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->limit(8)
            ->get();

        $limitedTimeOffers = Product::query()->publiclyAvailable()->with($this->productRelations())
            ->where('offer_ends_at', '>', now())
            ->whereHas('variants', fn ($variants) => $variants->available()
                ->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'))
            ->orderBy('offer_ends_at')
            ->limit(4)
            ->get();

        $trendingProducts = Product::query()->publiclyAvailable()->with($this->productRelations())
            ->where('is_trending', true)
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->limit(8)
            ->get();

        return view('elite-mobile-marketplace.home', array_merge($this->storefrontData(
            $recommendedProducts
        ), ['limitedTimeOffers' => $limitedTimeOffers, 'trendingProducts' => $trendingProducts]));
    }

    public function catalog(Request $request): View
    {
        return $this->catalogResponse($request);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($this->menuContains($this->categoryMenuService->visibleRootCategories(), $category->id), 404);

        return $this->catalogResponse($request, $category);
    }

    public function search(Request $request): View
    {
        return $this->catalogResponse($request);
    }

    public function compare(Request $request): View
    {
        $data = $request->validate([
            'products' => ['nullable', 'array', 'max:3'],
            'products.*' => ['required', 'string', 'max:255', 'distinct'],
        ]);
        $slugs = collect($data['products'] ?? [])->values();
        $comparisonProducts = Product::query()->publiclyAvailable()->with($this->productRelations())
            ->whereIn('slug', $slugs)
            ->get()
            ->sortBy(fn (Product $product) => $slugs->search($product->slug))
            ->values();

        return view('elite-mobile-marketplace.compare', [
            'activeTab' => 'shop',
            'menuCategories' => $this->categoryMenuService->visibleRootCategories(),
            'comparisonProducts' => $comparisonProducts,
        ]);
    }

    public function showProduct(Product $product): View
    {
        return $this->renderProduct($product);
    }

    public function showDeviceUnit(Product $product, DeviceUnit $deviceUnit): View
    {
        abort_unless($deviceUnit->variant()->where('product_id', $product->id)->exists()
            && $deviceUnit->status === DeviceUnitStatus::Available
            && Product::query()->whereKey($product->id)->publiclyAvailable()->exists(), 404);
        return $this->renderProduct($product, $deviceUnit);
    }

    public function usedPhones(Request $request): View
    {
        $request->merge(['condition' => DeviceConditionType::Used->value]);
        return $this->catalogResponse($request, null, 'Used Phones');
    }

    public function refurbishedPhones(Request $request): View
    {
        $request->merge(['condition' => DeviceConditionType::Refurbished->value]);
        return $this->catalogResponse($request, null, 'Refurbished Phones');
    }

    protected function renderProduct(Product $product, ?DeviceUnit $selectedDeviceUnit = null): View
    {
        abort_unless(Product::query()->whereKey($product->id)->publiclyAvailable()->exists(), 404);

        $product->load([
            'category', 'images', 'variants.images', 'variants.optionValues.productOption', 'variants.deviceUnits.images', 'installmentPlans',
        ]);

        $selectedDeviceUnit ??= $product->variants->flatMap->deviceUnits
            ->where('status', DeviceUnitStatus::Available)
            ->sortBy(fn (DeviceUnit $unit) => $unit->selling_price_cents)->first();

        $similarProducts = Product::query()
            ->publiclyAvailable()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with($this->productRelations())
            ->when(filled($product->brand), fn ($products) => $products->orderByRaw('CASE WHEN brand = ? THEN 0 ELSE 1 END', [$product->brand]))
            ->latest('published_at')
            ->limit(4)
            ->get();

        $initialVariant = $selectedDeviceUnit?->variant ?? $product->variants
            ->filter(fn (ProductVariant $variant) => $variant->is_available)
            ->sortBy('price')
            ->first()
            ?? $product->variants->filter(fn (ProductVariant $variant) => $variant->is_active)->sortBy('price')->first();

        $compatibleAccessories = collect();
        $compatibleDevices = collect();
        $checkerDevices = collect();

        if ($product->product_type === ProductType::Device) {
            $compatibleAccessories = $this->accessoryCompatibilityService
                ->compatibleAccessoriesForDevice($product)
                ->groupBy(fn (array $match) => $match['product']->accessory_subtype?->value ?? AccessorySubtype::Other->value);
        } elseif ($product->product_type === ProductType::Accessory) {
            $compatibleDevices = $this->accessoryCompatibilityService->compatibleDevicesForAccessory($product);
            $checkerDevices = Product::query()->publiclyAvailable()
                ->where('product_type', ProductType::Device->value)
                ->orderBy('brand')->orderBy('name')->get(['id', 'brand', 'name']);
        }

        return view('elite-mobile-marketplace.product-details', [
            'activeTab' => 'shop', 'productModel' => $product, 'isPreview' => false, 'similarProducts' => $similarProducts,
            'initialVariantPayload' => $initialVariant ? $this->variantPayload($product, $initialVariant, $selectedDeviceUnit?->product_variant_id === $initialVariant->id ? $selectedDeviceUnit : null) : null,
            'selectedDeviceUnit' => $selectedDeviceUnit,
            'availableDeviceUnits' => $product->variants->flatMap->deviceUnits->where('status', DeviceUnitStatus::Available)->values(),
            'menuCategories' => $this->categoryMenuService->visibleRootCategories(),
            'compatibleAccessories' => $compatibleAccessories,
            'compatibleDevices' => $compatibleDevices,
            'compatibilityCheckerDevices' => $checkerDevices,
        ]);
    }

    public function checkCompatibility(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->product_type === ProductType::Accessory
            && Product::query()->whereKey($product->id)->publiclyAvailable()->exists(), 404);

        $data = $request->validate(['device_id' => ['required', 'integer', 'exists:products,id']]);
        $device = Product::query()->publiclyAvailable()
            ->where('product_type', ProductType::Device->value)
            ->findOrFail($data['device_id']);

        return response()->json($this->accessoryCompatibilityService->determine($product, $device));
    }

    public function compatibleAccessories(Request $request): View
    {
        $data = $request->validate([
            'device' => ['required', 'integer', 'exists:products,id'],
            'subtype' => ['nullable', 'string', 'in:'.collect(AccessorySubtype::cases())->pluck('value')->implode(',')],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $device = Product::query()->publiclyAvailable()
            ->where('product_type', ProductType::Device->value)
            ->findOrFail($data['device']);
        $matches = $this->accessoryCompatibilityService->compatibleAccessoriesForDevice($device)
            ->when(filled($data['subtype'] ?? null), fn ($items) => $items->where('product.accessory_subtype.value', $data['subtype']))
            ->when(filled($data['category'] ?? null), fn ($items) => $items->filter(fn (array $match) => $match['product']->category?->slug === $data['category']))
            ->values();
        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = 12;
        $products = new LengthAwarePaginator(
            $matches->forPage($page, $perPage)->pluck('product')->values(),
            $matches->count(),
            $perPage,
            $page,
            ['path' => route('accessories.compatible'), 'query' => $request->query()]
        );

        return view('elite-mobile-marketplace.compatible-accessories', [
            'activeTab' => 'shop',
            'menuCategories' => $this->categoryMenuService->visibleRootCategories(),
            'device' => $device,
            'products' => $products,
            'subtypes' => AccessorySubtype::cases(),
            'categories' => Category::query()->where('is_active', true)->whereHas('products', fn ($query) => $query->where('product_type', ProductType::Accessory->value))->ordered()->get(['id', 'name', 'slug']),
        ]);
    }

    public function previewPurchase(Request $request, Product $product): JsonResponse
    {
        try {
            return response()->json($this->purchasePreview($request, $product));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'changed' => true], 422);
        }
    }

    public function confirmPurchase(Request $request, Product $product): JsonResponse
    {
        try {
            // Re-run the exact server lookup immediately before confirmation.
            $preview = $this->purchasePreview($request, $product);

            return response()->json([
                'confirmed' => true,
                'message' => 'Continue your installment application by providing your details and documents.',
                'application_url' => route('installments.create', [
                    'product_id' => $product->id,
                    'variant_id' => $preview['variant_id'],
                    'installment_months' => $preview['installment_months'],
                    'device_unit_id' => $preview['device_unit_id'],
                ]),
                'preview' => $preview,
            ]);
        } catch (DomainException $exception) {
            return response()->json(['confirmed' => false, 'message' => $exception->getMessage(), 'changed' => true], 422);
        }
    }

    /** Resolve by persisted option-value IDs; display labels are never part of this contract. */
    public function resolveVariant(Request $request, Product $product): JsonResponse
    {
        abort_unless(Product::query()->whereKey($product->id)->publiclyAvailable()->exists(), 404);

        $data = $request->validate([
            // A product may legitimately have a single, optionless variant.
            'option_value_ids' => ['present', 'array'],
            'option_value_ids.*' => ['required', 'integer', 'distinct'],
            'device_unit_id' => ['nullable', 'integer'],
        ]);
        $optionValueIds = collect($data['option_value_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();

        $variant = $product->variants()
            ->active()
            ->with(['images', 'optionValues.productOption', 'installmentPlans' => fn ($plans) => $plans->active()])
            ->where('option_signature', ProductVariant::buildOptionSignature($optionValueIds))
            ->first();

        if (! $variant) {
            return response()->json(['resolved' => false, 'message' => 'This option combination is unavailable.']);
        }

        $product->loadMissing(['images', 'installmentPlans' => fn ($plans) => $plans->active()]);

        $deviceUnit = filled($data['device_unit_id'] ?? null)
            ? DeviceUnit::query()->whereKey($data['device_unit_id'])->where('product_variant_id', $variant->id)->available()->with('images')->first()
            : null;
        return response()->json(array_merge(['resolved' => true], $this->variantPayload($product, $variant, $deviceUnit)));
    }

    protected function variantPayload(Product $product, \App\Models\ProductVariant $variant, ?DeviceUnit $deviceUnit = null): array
    {
        if ($variant->is_unit_managed) {
            $deviceUnit = $deviceUnit && $deviceUnit->product_variant_id === $variant->id && $deviceUnit->status === DeviceUnitStatus::Available
                ? $deviceUnit
                : $variant->availableDeviceUnits()->with('images')->orderByRaw('COALESCE(selling_price_override_cents, 9223372036854775807)')->first();
        }
        $plans = $this->installmentPlanService->availablePlansForVariant($product, $variant, $deviceUnit);

        $images = $this->productImageResolver->resolve($product, $variant, $deviceUnit);
        $price = $deviceUnit?->selling_price ?? (float) $variant->price;

        return [
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'price' => $price,
            'compare_at_price' => $variant->compare_at_price === null ? null : (float) $variant->compare_at_price,
            'stock_quantity' => $variant->available_stock,
            'in_stock' => $variant->is_available,
            'stock_message' => $variant->is_available ? ($variant->available_stock <= 5 ? "Only {$variant->available_stock} left in stock." : 'In stock.') : 'Out of stock.',
            'device_unit_id' => $deviceUnit?->id,
            'device' => $deviceUnit?->publicSnapshot(),
            'images' => $images->map(fn ($image) => [
                'url' => asset('storage/'.$image->image_path),
                'alt' => $image->alt_text ?: trim($product->name.' '.$variant->optionValues->pluck('display_name')->filter()->join(' ')),
            ])->values(),
            'plans' => $plans->map(function ($plan) use ($price) {
                $calculated = $this->installmentPlanService->previewFromPayload($plan->toArray(), $price, null, $price !== (float) $plan->total_amount);

                return [
                    'id' => $plan->id, 'payments' => $plan->number_of_payments, 'interval' => $plan->interval_type,
                    'amount_due_now' => $calculated['amount_due_now'], 'future_payment_count' => $calculated['future_payment_count'],
                    'installment_amount' => $calculated['installment_amount'], 'final_installment_amount' => $calculated['final_installment_amount'],
                    'financing_fee' => $calculated['financing_fee'], 'total' => $calculated['total_amount'], 'schedule' => $calculated['future_installments'],
                ];
            })->values(),
        ];
    }

    protected function purchasePreview(Request $request, Product $product): array
    {
        $data = $request->validate(['variant_id' => ['required', 'integer'], 'plan_id' => ['required', 'integer'], 'device_unit_id' => ['nullable', 'integer']]);
        $product = Product::query()->publiclyAvailable()->with(['variants.optionValues.productOption', 'variants.deviceUnits', 'installmentPlans'])->find($product->id)
            ?? throw new DomainException('This product is no longer available.');
        $variant = $product->variants->firstWhere('id', (int) $data['variant_id']);
        if (! $variant || ! $variant->is_active) throw new DomainException('The selected variant is no longer available.');
        if (! $variant->is_available) throw new DomainException('Stock changed: this variant is now out of stock.');
        $deviceUnit = null;
        if ($variant->is_unit_managed) {
            $deviceUnit = $variant->deviceUnits->firstWhere('id', (int) ($data['device_unit_id'] ?? 0));
            if (! $deviceUnit || $deviceUnit->status !== DeviceUnitStatus::Available) throw new DomainException('This exact device is no longer available.');
        }
        $plan = $this->installmentPlanService->availablePlansForVariant($product, $variant, $deviceUnit)->firstWhere('id', (int) $data['plan_id']);
        if (! $plan) throw new DomainException('Plan availability changed. Please select an available installment plan.');

        $price = $deviceUnit?->selling_price ?? (float) $variant->price;
        $calculated = $this->installmentPlanService->previewFromPayload($plan->toArray(), $price, CarbonImmutable::now(), $deviceUnit !== null);
        $options = $variant->optionValues->mapWithKeys(fn ($value) => [$value->productOption?->slug => $value->display_name ?: $value->name]);

        return ['product' => $product->name, 'variant_id' => $variant->id, 'device_unit_id' => $deviceUnit?->id, 'device' => $deviceUnit?->publicSnapshot(), 'option_value_ids' => $variant->optionValues->pluck('id')->sort()->values()->all(), 'variant_price' => $calculated['price'], 'storage' => $options->get('storage'), 'color' => $options->get('color'), 'plan_id' => $plan->id, 'installment_months' => $plan->number_of_payments] + $calculated;
    }

    // Legacy public URLs now use the database-backed catalog instead of demo fixtures.
    public function productDetails()
    {
        return redirect()->route('catalog.index');
    }

    public function installmentService()
    {
        return redirect()->route('catalog.index');
    }

    public function mobilesAccessories()
    {
        return redirect()->route('catalog.index');
    }

    protected function storefrontData($products, ?Category $category = null, string $searchTerm = ''): array
    {
        return [
            'activeTab' => 'shop',
            'menuCategories' => $this->categoryMenuService->visibleRootCategories(),
            'products' => $products,
            'currentCategory' => $category,
            'searchTerm' => $searchTerm,
        ];
    }

    protected function catalogResponse(Request $request, ?Category $category = null, ?string $pageTitle = null): View
    {
        $query = Product::query()->publiclyAvailable();
        $categoryIds = $category ? array_merge([$category->id], $category->descendantIds()) : [];

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($requestedCategory = $request->input('category')) {
            $filterCategory = Category::query()->where('slug', $requestedCategory)->where('is_active', true)->first();
            if ($filterCategory) {
                $query->whereIn('category_id', array_merge([$filterCategory->id], $filterCategory->descendantIds()));
            }
        }

        $term = trim((string) $request->input('q', ''));
        if ($term !== '') {
            $query->where(fn ($products) => $products->where('name', 'like', "%{$term}%")->orWhere('brand', 'like', "%{$term}%"));
        }

        $condition = trim((string) $request->input('condition', ''));
        if ($condition === DeviceConditionType::New->value) {
            $query->whereHas('variants', fn (Builder $variants) => $variants->available()->where('is_unit_managed', false));
        } elseif (in_array($condition, [DeviceConditionType::Used->value, DeviceConditionType::Refurbished->value], true)) {
            $query->whereHas('deviceUnits', fn (Builder $units) => $units->available()->where('condition_type', $condition));
        }
        if ($request->filled('grade')) $query->whereHas('deviceUnits', fn (Builder $units) => $units->available()->where('condition_grade', $request->input('grade')));
        if ($request->filled('battery_min')) $query->whereHas('deviceUnits', fn (Builder $units) => $units->available()->where('battery_health_percent', '>=', (int) $request->input('battery_min')));
        if ($request->input('warranty') === 'yes') $query->whereHas('deviceUnits', fn (Builder $units) => $units->available()->where(fn ($warranty) => $warranty->where('warranty_days', '>', 0)->orWhere('warranty_until', '>=', today())));

        foreach (['brand', 'storage', 'color'] as $filter) {
            $value = trim((string) $request->input($filter, ''));
            if ($value === '') {
                continue;
            }

            if ($filter === 'brand') {
                $query->where('brand', $value);
                continue;
            }

            $query->whereHas('variants', fn ($variants) => $variants->available()->whereHas('optionValues', fn ($values) => $values->active()
                ->where('name', $value)
                ->whereHas('productOption', fn ($option) => $option->where('slug', $filter)->where('is_active', true))));
        }

        foreach (['price_min' => '>=', 'price_max' => '<='] as $input => $operator) {
            if (is_numeric($request->input($input))) {
                $amount = (float) $request->input($input);
                if (in_array($condition, [DeviceConditionType::Used->value, DeviceConditionType::Refurbished->value], true)) {
                    $query->whereHas('deviceUnits', fn (Builder $units) => $units->available()->where('condition_type', $condition)
                        ->where(fn (Builder $prices) => $prices->where('selling_price_override_cents', $operator, (int) round($amount * 100))
                            ->orWhere(fn (Builder $fallback) => $fallback->whereNull('selling_price_override_cents')->whereHas('variant', fn (Builder $variant) => $variant->where('price', $operator, $amount)))));
                } else {
                    $query->whereHas('variants', fn ($variants) => $variants->available()->where('price', $operator, $amount));
                }
            }
        }

        if ($request->input('availability') === 'in_stock') {
            $query->whereHas('variants', fn ($variants) => $variants->available());
        }

        if (is_numeric($request->input('payments'))) {
            $count = (int) $request->input('payments');
            $query->whereHas('installmentPlans', fn ($plans) => $plans->active()->where('number_of_payments', $count)
                ->where(fn ($plans) => $plans->whereNull('product_variant_id')->orWhereHas('variant', fn ($variants) => $variants->available())));
            if (in_array($condition, [DeviceConditionType::Used->value, DeviceConditionType::Refurbished->value], true)) {
                $query->whereHas('deviceUnits', fn (Builder $units) => $units->available()->where('condition_type', $condition)->where('installments_enabled', true));
            }
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderByRaw('(select min(price) from product_variants where product_variants.product_id = products.id and is_active = 1 and stock_quantity > 0) asc'),
            'price_desc' => $query->orderByRaw('(select max(price) from product_variants where product_variants.product_id = products.id and is_active = 1 and stock_quantity > 0) desc'),
            'installment_asc' => $query->orderByRaw('(select min(installment_amount) from installment_plans where installment_plans.product_id = products.id and is_active = 1) asc'),
            'name_asc' => $query->orderBy('name'),
            default => $query->orderByRaw('COALESCE(published_at, created_at) DESC'),
        };

        $products = $query->with($this->productRelations())->paginate(18)->withQueryString();

        return view('elite-mobile-marketplace.catalog', array_merge(
            $this->storefrontData($products, $category, $term),
            ['filterOptions' => $this->filterOptions(), 'childCategories' => $category ? $this->visibleChildren($category) : collect(), 'selectedSort' => $sort, 'pageTitle' => $pageTitle]
        ));
    }

    protected function filterOptions(): array
    {
        $available = Product::query()->publiclyAvailable();

        return [
            'categories' => Category::query()->where('is_active', true)->ordered()->get(['id', 'name', 'slug']),
            'brands' => (clone $available)->whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
            'storage' => $this->availableOptionNames(ProductOption::STORAGE_SLUG),
            'color' => $this->availableOptionNames(ProductOption::COLOR_SLUG),
            'payments' => InstallmentPlan::query()->active()
                ->whereHas('product', fn ($products) => $products->publiclyAvailable())
                ->where(fn ($plans) => $plans->whereNull('product_variant_id')->orWhereHas('variant', fn ($variants) => $variants->available()))
                ->distinct()->orderBy('number_of_payments')->pluck('number_of_payments'),
            'conditions' => DeviceConditionType::cases(),
            'grades' => \App\Enums\DeviceConditionGrade::cases(),
        ];
    }

    protected function availableOptionNames(string $optionSlug)
    {
        return \App\Models\ProductOptionValue::query()->active()
            ->whereHas('productOption', fn ($options) => $options->where('slug', $optionSlug)->where('is_active', true))
            ->whereHas('variants', fn ($variants) => $variants->available()->whereHas('product', fn ($products) => $products->publiclyAvailable()))
            ->orderBy('name')->pluck('name')->unique()->values();
    }

    protected function visibleChildren(Category $category)
    {
        return $category->children()->where('is_active', true)->whereHas('products', fn ($products) => $products->publiclyAvailable())->get();
    }

    protected function productRelations(): array
    {
        return [
            'category:id,name,slug', 'images',
            'variants' => fn ($query) => $query->available()->with(['deviceUnits' => fn ($units) => $units->available()->with('images'), 'optionValues' => fn ($values) => $values->active()->whereHas('productOption', fn ($options) => $options->where('is_active', true))->with('productOption')])->orderBy('price'),
            'installmentPlans' => fn ($query) => $query->active(),
        ];
    }

    protected function menuContains(iterable $categories, int $categoryId): bool
    {
        foreach ($categories as $menuCategory) {
            if ($menuCategory->id === $categoryId || $this->menuContains($menuCategory->childrenRecursive, $categoryId)) {
                return true;
            }
        }

        return false;
    }
}
