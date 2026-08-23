<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\InstallmentApplication;
use App\Models\Order;
use App\Models\Product;
use App\Policies\CategoryPolicy;
use App\Policies\InstallmentApplicationPolicy;
use App\Policies\PosOrderPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(InstallmentApplication::class, InstallmentApplicationPolicy::class);
        Gate::policy(Order::class, PosOrderPolicy::class);

        Gate::define('access-admin', fn ($user) => $user->canAccessAdmin());
        Gate::define('access-pos', fn ($user) => $user->canAccessPos());
        Gate::define('refund-pos-sales', fn ($user) => $user->canRefundPosSales());
    }
}
