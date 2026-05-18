<?php

namespace App\Http\Middleware;

use App\Models\Central\User;
use App\Models\Tenant\Company;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Database\Eloquent\Collection;
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
        $isTenant = isTenant();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $this->serializeUser(user()),
            ],
            'currentCompany' => $isTenant && session('current_company_id')
                ? Company::find(session('current_company_id'), ['id', 'ruc', 'name'])
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

    private function serializeUser(User|TenantUser|null $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? null,
            'permissions' => $this->resolvePermissions($user),
            'has_active_subscription' => method_exists($user, 'hasActiveSubscription')
                ? $user->hasActiveSubscription()
                : null,
        ];
    }

    private function resolvePermissions(User|TenantUser $user): array
    {
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return [['permission' => '*', 'model' => '*']];
        }

        $modelPermissions = $this->loadModelPermissions($user);

        if (! $modelPermissions) {
            return [];
        }

        return $modelPermissions->map(fn ($mp) => [
            'permission' => $mp->permission->slug,
            'model' => $mp->modelEntity->slug,
        ])->values()->all();
    }

    private function loadModelPermissions(User|TenantUser $user): ?Collection
    {
        if ($user instanceof User && isTenant()) {
            return TenantUser::where('central_user_id', $user->id)
                ->with('role.modelPermissions.permission', 'role.modelPermissions.modelEntity')
                ->first()
                ?->role?->modelPermissions;
        }

        if ($user instanceof User && $user->role) {
            $user->load('role.modelPermissions.permission', 'role.modelPermissions.modelEntity');

            return $user->role->modelPermissions;
        }

        if ($user instanceof TenantUser && $user->role_id) {
            $user->load('role.modelPermissions.permission', 'role.modelPermissions.modelEntity');

            return $user->role?->modelPermissions;
        }

        return null;
    }
}
