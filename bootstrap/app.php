<?php


use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        using: function () {
            foreach (config('tenancy.central_domains') as $domain) {
                Route::middleware('web')
                    ->domain($domain)
                    ->group(base_path('routes/web.php'));
            }
        }

    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            App\Http\Middleware\HandleInertiaRequests::class,
            Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role'                      => \App\Http\Middleware\RoleMiddleware::class,
            'check.active'              => \App\Http\Middleware\CheckActive::class,
            'central.only'              => \App\Http\Middleware\EnsureCentralUser::class,
            'auth.tenant'               => \App\Http\Middleware\AuthTenant::class,
            'guest.tenant'              => \App\Http\Middleware\GuestTenant::class,
            'check.tenant.subscription' => \App\Http\Middleware\CheckTenantSubscription::class,
            'tenant.role'               => \App\Http\Middleware\TenantRoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
