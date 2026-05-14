<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\Tenant\AccountController;
use App\Http\Controllers\Tenant\AtsController;
use App\Http\Controllers\Tenant\CompanyController;
use App\Http\Controllers\Tenant\CompanyScopeController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\EmployeeController;
use App\Http\Controllers\Tenant\OrderController;
use App\Http\Controllers\Tenant\ProfileController as TenantProfileController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\RetentionController;
use App\Http\Controllers\Tenant\ShopController;
use App\Http\Controllers\Tenant\SriScrapeController;
use App\Http\Middleware\Tenant\RequireCompanyScope;
use Illuminate\Support\Facades\Route;

Route::get('/auth/sso', [SsoController::class, 'handle'])
    ->name('tenant.sso');

Route::middleware(['auth.tenant', 'check.tenant.subscription'])->group(function () {

    Route::middleware(RequireCompanyScope::class)->group(function () {

        Route::get('reports/shops-by-account', [ReportController::class, 'shopsByAccount'])
            ->name('tenant.reports.shops-by-account');
        Route::get('reports/shops-by-account/export', [ReportController::class, 'exportShopsByAccount'])
            ->name('tenant.reports.shops-by-account.export');
        Route::get('reports/shops-by-voucher-type', [ReportController::class, 'shopsByVoucherType'])
            ->name('tenant.reports.shops-by-voucher-type');
        Route::get('reports/shops-by-voucher-type/export', [ReportController::class, 'exportShopsByVoucherType'])
            ->name('tenant.reports.shops-by-voucher-type.export');
        Route::get('reports/shops-by-provider', [ReportController::class, 'shopsByProvider'])
            ->name('tenant.reports.shops-by-provider');
        Route::get('reports/shops-by-provider/export', [ReportController::class, 'exportShopsByProvider'])
            ->name('tenant.reports.shops-by-provider.export');
        Route::get('reports/shops-by-retention', [ReportController::class, 'shopsByRetention'])
            ->name('tenant.reports.shops-by-retention');
        Route::get('reports/shops-by-retention/export', [ReportController::class, 'exportShopsByRetention'])
            ->name('tenant.reports.shops-by-retention.export');

        Route::get('reports/orders-by-voucher-type', [ReportController::class, 'ordersByVoucherType'])
            ->name('tenant.reports.orders-by-voucher-type');
        Route::get('reports/orders-by-voucher-type/export', [ReportController::class, 'exportOrdersByVoucherType'])
            ->name('tenant.reports.orders-by-voucher-type.export');
        Route::get('reports/orders-by-client', [ReportController::class, 'ordersByClient'])
            ->name('tenant.reports.orders-by-client');
        Route::get('reports/orders-by-client/export', [ReportController::class, 'exportOrdersByClient'])
            ->name('tenant.reports.orders-by-client.export');

        Route::get('export-ats', [AtsController::class, 'export'])->name('tenant.export-ats');
        Route::post('import-ats', [AtsController::class, 'import'])->name('tenant.import-ats');
        Route::get('orders/export', [OrderController::class, 'export'])->name('tenant.orders.export');
        Route::post('orders/import', [OrderController::class, 'import'])->name('tenant.orders.import');
        Route::post('orders/import-retentions', [OrderController::class, 'importRetentions'])->name('tenant.orders.import-retentions');

        Route::resource('orders', OrderController::class)
            ->except(['show'])
            ->names([
                'index' => 'tenant.orders.index',
                'create' => 'tenant.orders.create',
                'store' => 'tenant.orders.store',
                'edit' => 'tenant.orders.edit',
                'update' => 'tenant.orders.update',
                'destroy' => 'tenant.orders.destroy',
            ]);

        Route::get('orders/{order}', [OrderController::class, 'show'])->name('tenant.orders.show');
        Route::post('orders/{order}/retention', [OrderController::class, 'storeRetention'])
            ->name('tenant.orders.retention.store');

        Route::get('accounts', [AccountController::class, 'index'])->name('tenant.accounts.index');
        Route::post('accounts/import', [AccountController::class, 'import'])->name('tenant.accounts.import');
        Route::get('accounts/search', [AccountController::class, 'search'])->name('tenant.accounts.search');

        Route::get('retentions/search', [RetentionController::class, 'search'])->name('tenant.retentions.search');

        Route::get('shops/export', [ShopController::class, 'export'])->name('tenant.shops.export');
        Route::post('shops/import', [ShopController::class, 'import'])->name('tenant.shops.import');
        Route::post('shops/import-retentions', [ShopController::class, 'importRetentions'])->name('tenant.shops.import-retentions');
        Route::post('shops/complete-retentions', [ShopController::class, 'completeRetentions'])->name('tenant.shops.complete-retentions');
        Route::resource('shops', ShopController::class)
            ->names([
                'index' => 'tenant.shops.index',
                'create' => 'tenant.shops.create',
                'store' => 'tenant.shops.store',
                'show' => 'tenant.shops.show',
                'edit' => 'tenant.shops.edit',
                'update' => 'tenant.shops.update',
                'destroy' => 'tenant.shops.destroy',
            ]);
        Route::post('shops/{shop}/retention', [ShopController::class, 'storeRetention'])
            ->name('tenant.shops.retention.store');
        Route::patch('shops/{shop}/account', [ShopController::class, 'updateAccount'])
            ->name('tenant.shops.account.update');

        Route::get('sri-scrape', [SriScrapeController::class, 'index'])->name('tenant.sri-scrape.index');
        Route::post('sri-scrape', [SriScrapeController::class, 'store'])->name('tenant.sri-scrape.store');
        Route::get('sri-scrape/status', [SriScrapeController::class, 'status'])->name('tenant.sri-scrape.status');

        Route::get('sri', [AtsController::class, 'index'])->name('tenant.sri.index');
    });

    Route::get('/company-scope/select', [CompanyScopeController::class, 'select'])
        ->name('tenant.company-scope.select');

    Route::get('/company-scope/search', [CompanyScopeController::class, 'search'])
        ->name('tenant.company-scope.search');

    Route::post('/company-scope', [CompanyScopeController::class, 'store'])
        ->name('tenant.company-scope.store');

    Route::resource('companies', CompanyController::class)
        ->except(['show'])
        ->names([
            'index' => 'tenant.companies.index',
            'create' => 'tenant.companies.create',
            'store' => 'tenant.companies.store',
            'edit' => 'tenant.companies.edit',
            'update' => 'tenant.companies.update',
            'destroy' => 'tenant.companies.destroy',
        ]);

    Route::get('companies/resolve/{identification}', [CompanyController::class, 'resolve'])->name('tenant.companies.resolve');
    Route::get('/contacts', [ContactController::class, 'index'])->name('tenant.contacts.index');
    Route::post('/contacts', [ContactController::class, 'store'])->name('tenant.contacts.store');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('tenant.contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('tenant.contacts.destroy');
    Route::get('contacts/search/{identification}', [ContactController::class, 'search'])->name('tenant.contacts.search');
    Route::get('contacts/resolve/{identification}', [ContactController::class, 'resolve'])->name('tenant.contacts.resolve');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('tenant.logout');

    Route::get('/dashboard', TenantDashboardController::class)
        ->name('tenant.dashboard');

    Route::get('/profile', [TenantProfileController::class, 'edit'])
        ->name('tenant.profile.edit');
    Route::patch('/profile/info', [TenantProfileController::class, 'updateInfo'])
        ->name('tenant.profile.update-info');
    Route::patch('/profile/password', [TenantProfileController::class, 'updatePassword'])
        ->name('tenant.profile.update-password');

    Route::middleware('tenant.role:admin')->group(function () {
        Route::resource('employees', EmployeeController::class)
            ->except(['show', 'create', 'edit']);
        Route::patch('employees/{employee}/toggle-active', [EmployeeController::class, 'toggleActive'])
            ->name('employees.toggle-active');
    });
});
