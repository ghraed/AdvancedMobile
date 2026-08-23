<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstallmentApplicationController as AdminInstallmentApplicationController;
use App\Http\Controllers\Admin\InstallmentPlanController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfitAnalyticsController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\EliteMobileMarketplaceController;
use App\Http\Controllers\InstallmentApplicationController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EliteMobileMarketplaceController::class, 'home']);
Route::get('/home', [EliteMobileMarketplaceController::class, 'home']);
Route::get('/catalog', [EliteMobileMarketplaceController::class, 'catalog'])->name('catalog.index');
Route::get('/categories/{category:slug}', [EliteMobileMarketplaceController::class, 'category'])->name('categories.show');
Route::get('/search', [EliteMobileMarketplaceController::class, 'search'])->name('search');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
Route::get('/compare', [EliteMobileMarketplaceController::class, 'compare'])->name('products.compare');
Route::get('/product-details', [EliteMobileMarketplaceController::class, 'productDetails']);
Route::get('/products/{product:slug}', [EliteMobileMarketplaceController::class, 'showProduct'])->name('products.show');
Route::post('/products/{product:slug}/resolve-variant', [EliteMobileMarketplaceController::class, 'resolveVariant'])->name('products.resolve-variant');
Route::post('/products/{product:slug}/purchase-preview', [EliteMobileMarketplaceController::class, 'previewPurchase'])->name('products.purchase-preview');
Route::post('/products/{product:slug}/confirm-purchase', [EliteMobileMarketplaceController::class, 'confirmPurchase'])->name('products.confirm-purchase');
Route::middleware('guest')->group(function () {
    Route::get('/sign-in', [CustomerAuthController::class, 'login'])->name('customer.login');
    Route::post('/sign-in', [CustomerAuthController::class, 'authenticate'])->name('customer.login.store');
    Route::get('/register', [CustomerAuthController::class, 'register'])->name('customer.register');
    Route::post('/register', [CustomerAuthController::class, 'store'])->name('customer.register.store');
});
Route::get('/checkout', [CheckoutController::class, 'show'])->middleware('auth')->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('auth')->name('checkout.store');
Route::get('/orders/{order}/confirmation', [CheckoutController::class, 'confirmation'])->middleware('auth')->name('orders.confirmation');
Route::get('/installment-service', [InstallmentApplicationController::class, 'landing'])->name('installments.landing');
Route::get('/installments/apply', [InstallmentApplicationController::class, 'create'])->name('installments.create');
Route::post('/installments/quote', [InstallmentApplicationController::class, 'quote'])->name('installments.quote');
Route::post('/installments', [InstallmentApplicationController::class, 'store'])->name('installments.store');
Route::get('/installments/{application}/success', [InstallmentApplicationController::class, 'success'])->name('installments.success');
Route::middleware('auth')->group(function () {
    Route::get('/my-installment-applications', [InstallmentApplicationController::class, 'index'])->name('installments.index');
    Route::get('/my-installment-applications/{application}', [InstallmentApplicationController::class, 'show'])->name('installments.show');
    Route::get('/my-installment-applications/{application}/documents/{document}', [InstallmentApplicationController::class, 'document'])->name('installments.documents.show');
});
Route::get('/mobiles-accessories', [EliteMobileMarketplaceController::class, 'mobilesAccessories']);

Route::prefix('elite-mobile-marketplace')->name('elite-mobile-marketplace.')->group(function () {
    Route::get('/home', [EliteMobileMarketplaceController::class, 'home'])->name('home');
    Route::get('/product-details', [EliteMobileMarketplaceController::class, 'productDetails'])->name('product-details');
    Route::get('/installment-service', [EliteMobileMarketplaceController::class, 'installmentService'])->name('installment-service');
    Route::get('/mobiles-accessories', [EliteMobileMarketplaceController::class, 'mobilesAccessories'])->name('mobiles-accessories');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'can:access-pos'])->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/products/search', [PosController::class, 'search'])->name('products.search');
        Route::post('/checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::get('/sales', [PosController::class, 'sales'])->name('sales.index');
        Route::get('/sales/{order}', [PosController::class, 'show'])->name('sales.show');
        Route::get('/sales/{order}/receipt', [PosController::class, 'receipt'])->name('sales.receipt');
        Route::post('/sales/{order}/refund', [PosController::class, 'refund'])
            ->middleware('can:refund-pos-sales')
            ->name('sales.refund');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/analytics/profit', [ProfitAnalyticsController::class, 'index'])->name('analytics.profit');
        Route::get('/analytics/profit/export', [ProfitAnalyticsController::class, 'export'])->name('analytics.profit.export');
        Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/account', [AccountController::class, 'update'])->name('account.update');
        Route::get('/installment-plans', [InstallmentPlanController::class, 'index'])->name('installment-plans.index');
        Route::get('/installment-applications', [AdminInstallmentApplicationController::class, 'index'])->name('installment-applications.index');
        Route::get('/installment-applications/{installmentApplication}', [AdminInstallmentApplicationController::class, 'show'])->name('installment-applications.show');
        Route::patch('/installment-applications/{installmentApplication}/status', [AdminInstallmentApplicationController::class, 'transition'])->name('installment-applications.transition');
        Route::get('/installment-applications/{installmentApplication}/documents/{document}/preview', [AdminInstallmentApplicationController::class, 'previewDocument'])->name('installment-applications.documents.preview');
        Route::get('/installment-applications/{installmentApplication}/documents/{document}', [AdminInstallmentApplicationController::class, 'document'])->name('installment-applications.documents.show');
        Route::post('/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::patch('/categories/{category}/activate', [CategoryController::class, 'activate'])->name('categories.activate');
        Route::patch('/categories/{category}/deactivate', [CategoryController::class, 'deactivate'])->name('categories.deactivate');
        Route::patch('/products/{product}/activate', [ProductController::class, 'activate'])->name('products.activate');
        Route::patch('/products/{product}/deactivate', [ProductController::class, 'deactivate'])->name('products.deactivate');
        Route::post('/products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
        Route::get('/products/{product}/preview', [ProductController::class, 'preview'])->name('products.preview');
        Route::post('/products/installment-preview', [ProductController::class, 'previewInstallmentPlan'])->name('products.installment-preview.create');
        Route::post('/products/{product}/installment-preview', [ProductController::class, 'previewInstallmentPlan'])->name('products.installment-preview');
        Route::resource('categories', CategoryController::class);
        Route::resource('products', ProductController::class)->except('show');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    });
});
