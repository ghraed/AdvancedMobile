<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InstallmentPlanController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('access-admin');

        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status', 'all');

        $products = Product::query()
            ->with(['category', 'installmentPlans'])
            ->withCount('installmentPlans')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($status === 'without-plans', fn ($query) => $query->doesntHave('installmentPlans'))
            ->when($status === 'with-plans', fn ($query) => $query->has('installmentPlans'))
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('admin.installment-plans.index', [
            'products' => $products,
            'filters' => compact('search', 'status'),
        ]);
    }
}
