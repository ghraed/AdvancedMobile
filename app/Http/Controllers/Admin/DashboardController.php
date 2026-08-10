<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\InstallmentApplication;
use App\Models\Category;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('access-admin');

        return view('admin.dashboard', [
            'stats' => [
                'categories' => Category::count(),
                'active_categories' => Category::query()->where('is_active', true)->count(),
                'products' => Product::count(),
                'active_products' => Product::query()->where('status', ProductStatus::Active)->count(),
                'variants' => ProductVariant::count(),
                'low_stock_variants' => ProductVariant::query()->whereBetween('stock_quantity', [1, 5])->count(),
                'out_of_stock_variants' => ProductVariant::query()->where('stock_quantity', 0)->count(),
                'products_without_installment_plans' => Product::query()->doesntHave('installmentPlans')->count(),
            ],
            'latestProducts' => Product::query()
                ->with('category')
                ->latest('id')
                ->take(8)
                ->get(),
            'installmentPlanCount' => InstallmentPlan::count(),
            'installmentApplicationCount' => InstallmentApplication::count(),
            'submittedInstallmentApplicationCount' => InstallmentApplication::query()
                ->where('status', 'submitted')
                ->count(),
        ]);
    }
}
