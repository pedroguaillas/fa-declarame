<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'                      => $user->id,
                    'name'                    => $user->name,
                    'email'                   => $user->email,
                    'role'                    => $user->role ?? null,
                    'has_active_subscription' => method_exists($user, 'hasActiveSubscription')
                        ? $user->hasActiveSubscription()
                        : null,
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'tenant' => currentTenant() ? [
                'id'   => currentTenant()->id,
                'name' => currentTenant()->name,
            ] : null,
        ]);
    }
}
