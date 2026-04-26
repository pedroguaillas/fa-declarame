<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Tenant\CompanyController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\Tenant\CompanyScopeController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\EmployeeController;
use App\Http\Controllers\Tenant\ProfileController as TenantProfileController;
use App\Http\Controllers\Tenant\ShopController;
use App\Http\Middleware\Tenant\RequireCompanyScope;
use Illuminate\Support\Facades\Route;


Route::get('/auth/sso', [SsoController::class, 'handle'])
    ->name('tenant.sso');


Route::middleware(['auth.tenant', 'check.tenant.subscription'])->group(function () {


    Route::middleware(RequireCompanyScope::class)->group(function () {


        Route::post('shops/import', [ShopController::class, 'import'])->name('tenant.shops.import');
        Route::post('shops/import-retentions', [ShopController::class, 'importRetentions'])->name('tenant.shops.import-retentions');

        Route::resource('shops', ShopController::class)
            ->except(['show'])
            ->names([
                'index' => 'tenant.shops.index',
                'create' => 'tenant.shops.create',
                'store' => 'tenant.shops.store',
                'edit' => 'tenant.shops.edit',
                'update' => 'tenant.shops.update',
                'destroy' => 'tenant.shops.destroy',
            ]);

        Route::post('shops/{shop}/retention', [ShopController::class, 'storeRetention'])
            ->name('tenant.shops.retention.store');

        Route::patch('shops/{shop}/account', [ShopController::class, 'updateAccount'])
            ->name('tenant.shops.account.update');
    });


    Route::get('/company-scope/select', [CompanyScopeController::class, 'select'])
        ->name('tenant.company-scope.select');

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
