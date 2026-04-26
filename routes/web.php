<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModelEntityController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
    ]);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'check.active', 'central.only'])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/info', [ProfileController::class, 'updateInfo'])->name('profile.update-info');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');

    Route::middleware('role:super_admin')->group(function () {

        Route::resource('tenants', TenantController::class)
            ->except(['show', 'create', 'edit']);

        Route::resource('users', UserController::class)
            ->except(['show', 'create', 'edit']);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');

        Route::get('subscriptions/history/{user}', [SubscriptionController::class, 'history'])
            ->name('subscriptions.history');
        Route::resource('subscriptions', SubscriptionController::class)
            ->except(['show', 'create', 'edit']);
        Route::patch('subscriptions/{subscription}/toggle-active', [SubscriptionController::class, 'toggleActive'])
            ->name('subscriptions.toggle-active');

        Route::resource('plans', PlanController::class)
            ->except(['show', 'create', 'edit']);
        Route::patch('plans/{plan}/toggle-active', [PlanController::class, 'toggleActive'])
            ->name('plans.toggle-active');

        Route::resource('roles', RoleController::class)
            ->except(['show']);
        Route::resource('permissions', PermissionController::class)
            ->except(['show', 'create', 'edit']);
        Route::resource('model-entities', ModelEntityController::class)
            ->except(['show', 'create', 'edit']);
    });
});
