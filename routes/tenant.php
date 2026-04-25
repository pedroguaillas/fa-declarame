<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\TenantAuthController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\EmployeeController;
use App\Http\Controllers\Tenant\ProfileController as TenantProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('tenant.login'));

Route::middleware('guest.tenant')->group(function () {
    Route::get('/login', [TenantAuthController::class, 'showLogin'])
        ->name('tenant.login');
    Route::post('/login', [TenantAuthController::class, 'login'])
        ->name('tenant.login.submit');
});

Route::middleware(['auth.tenant', 'check.tenant.subscription'])->group(function () {
    Route::post('/logout', [TenantAuthController::class, 'logout'])
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
