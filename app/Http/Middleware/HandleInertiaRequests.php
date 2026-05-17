<?php

namespace App\Http\Middleware;

use App\Models\Tenant\Company;
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
        $currentCompanyId = session('current_company_id');
        $isTenant = isTenant();

        $permissions = [];
        if ($user) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                $permissions = [['permission' => '*', 'model' => '*']];
            } elseif (method_exists($user, 'role') && $user->role) {
                $user->load('role.modelPermissions.permission', 'role.modelPermissions.modelEntity');
                $permissions = $user->role->modelPermissions->map(fn ($mp) => [
                    'permission' => $mp->permission->slug,
                    'model' => $mp->modelEntity->slug,
                ])->values()->all();
            }
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? null,
                    'permissions' => $permissions,
                    'has_active_subscription' => method_exists($user, 'hasActiveSubscription')
                        ? $user->hasActiveSubscription()
                        : null,
                ] : null,
            ],
            'currentCompany' => $isTenant && $currentCompanyId
                ? Company::find($currentCompanyId, ['id', 'ruc', 'name'])
                : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'failedKeys' => $request->session()->get('failed_keys', []),
            ],
            'tenant' => currentTenant() ? [
                'id' => currentTenant()->id,
                'name' => currentTenant()->name,
            ] : null,
        ]);
    }
}
