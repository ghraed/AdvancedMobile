<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PosCheckoutRequest;
use App\Http\Requests\PosRefundRequest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\PosCheckoutService;
use App\Services\PosRefundService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected PosCheckoutService $checkoutService,
        protected PosRefundService $refundService,
    ) {}

    public function index(): View
    {
        return view('admin.pos.index', [
            'initialProducts' => $this->searchResults(''),
            'checkoutToken' => (string) str()->uuid(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        return response()->json(['data' => $this->searchResults(trim((string) ($validated['q'] ?? '')))]);
    }

    public function checkout(PosCheckoutRequest $request): JsonResponse
    {
        try {
            $sale = $this->checkoutService->checkout($request->user(), $request->validated());

            return response()->json([
                'message' => 'Sale completed successfully.',
                'order' => ['id' => $sale->id, 'reference' => $sale->reference, 'total_cents' => $sale->total_cents],
                'sale_url' => route('admin.pos.sales.show', $sale),
                'receipt_url' => route('admin.pos.sales.receipt', $sale),
            ]);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function sales(Request $request): View
    {
        $filters = $request->validate([
            'reference' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'mixed'])],
            'cashier_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'product' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['completed', 'refunded'])],
        ]);

        $sales = Order::query()
            ->where('sales_channel', 'pos')
            ->with(['cashier:id,name', 'payments:id,order_id,payment_method,amount_cents,status'])
            ->withSum('items as item_count', 'quantity')
            ->when(filled($filters['reference'] ?? null), fn (Builder $query) => $query->where('reference', 'like', '%'.trim($filters['reference']).'%'))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->when(filled($filters['cashier_id'] ?? null), fn (Builder $query) => $query->where('cashier_id', $filters['cashier_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['product'] ?? null), function (Builder $query) use ($filters): void {
                $term = trim($filters['product']);
                $query->whereHas('items', fn (Builder $items) => $items
                    ->where('product_name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%"));
            })
            ->when(filled($filters['payment_method'] ?? null), function (Builder $query) use ($filters): void {
                $method = $filters['payment_method'];
                if ($method === 'mixed') {
                    $query->whereHas('payments', fn (Builder $payments) => $payments->where('payment_method', 'cash'))
                        ->whereHas('payments', fn (Builder $payments) => $payments->where('payment_method', 'card'));
                } else {
                    $query->whereHas('payments', fn (Builder $payments) => $payments->where('payment_method', $method))
                        ->whereDoesntHave('payments', fn (Builder $payments) => $payments->where('payment_method', '!=', $method));
                }
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pos.sales.index', [
            'sales' => $sales,
            'filters' => $filters,
            'cashiers' => User::query()->whereHas('posSales')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Order $order): View
    {
        $this->authorize('viewPos', $order);

        return view('admin.pos.sales.show', ['sale' => $order->load(['items', 'payments', 'cashier', 'refunds'])]);
    }

    public function receipt(Order $order): View
    {
        $this->authorize('viewPos', $order);

        return view('admin.pos.sales.receipt', ['sale' => $order->load(['items', 'payments', 'cashier', 'refunds'])]);
    }

    public function refund(PosRefundRequest $request, Order $order): RedirectResponse|JsonResponse
    {
        try {
            $refund = $this->refundService->refund($order, $request->user(), $request->validated('reason'));
        } catch (DomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Sale refunded.', 'refund' => ['reference' => $refund->reference]]);
        }

        return redirect()->route('admin.pos.sales.show', $order)->with('status', 'Sale refunded and stock restored.');
    }

    private function searchResults(string $term): array
    {
        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->with([
                'product:id,category_id,name,brand,status',
                'product.category:id,is_active',
                'product.images:id,product_id,product_option_value_id,product_variant_id,image_path,is_primary,sort_order',
                'images:id,product_id,product_variant_id,image_path,is_primary,sort_order',
                'optionValues:id,product_option_id,name,display_name,is_active',
                'optionValues.productOption:id,name,slug,sort_order,is_active',
            ])
            ->where('product_variants.is_active', true)
            ->where('product_variants.stock_quantity', '>', 0)
            ->whereHas('product', fn (Builder $products) => $products
                ->where('status', ProductStatus::Active)
                ->whereHas('category', fn (Builder $categories) => $categories->where('is_active', true)))
            ->whereDoesntHave('optionValues', fn (Builder $values) => $values
                ->where('is_active', false)
                ->orWhereHas('productOption', fn (Builder $options) => $options->where('is_active', false)))
            ->when($term !== '', function (Builder $query) use ($term): void {
                $query->where(function (Builder $search) use ($term): void {
                    $search->where('product_variants.sku', 'like', "%{$term}%")
                        ->orWhere('product_variants.barcode', 'like', "%{$term}%")
                        ->orWhereHas('product', fn (Builder $products) => $products
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('brand', 'like', "%{$term}%"));
                });
            })
            ->when($term !== '', fn (Builder $query) => $query->orderByRaw('CASE WHEN barcode = ? THEN 0 WHEN sku = ? THEN 1 ELSE 2 END', [$term, $term]))
            ->orderBy('product_id')
            ->orderBy('id')
            ->limit(30)
            ->get();

        return $variants->map(function (ProductVariant $variant): array {
            $image = $variant->images->first() ?? $variant->product->images->first();

            return [
                'variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'brand' => $variant->product->brand,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price_cents' => $this->decimalToCents((string) $variant->price),
                'stock_quantity' => $variant->stock_quantity,
                'is_active' => $variant->is_active,
                'options' => $variant->optionValues
                    ->sortBy(fn ($value) => $value->productOption?->sort_order ?? 0)
                    ->map(fn ($value) => $value->display_name ?: $value->name)->values()->all(),
                'image_url' => $image ? Storage::disk('public')->url($image->image_path) : null,
            ];
        })->all();
    }

    private function decimalToCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) substr(str_pad($fraction, 2, '0'), 0, 2);
    }
}
