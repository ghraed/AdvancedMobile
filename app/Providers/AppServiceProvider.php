<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Models\InstallmentApplication;
use App\Policies\CategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\InstallmentApplicationPolicy;
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

        Gate::define('access-admin', fn ($user) => $user->canAccessAdmin());
    }
}
