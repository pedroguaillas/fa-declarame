<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\User\StoreUserRequest;
use App\Http\Requests\Central\User\UpdateUserRequest;
use App\Models\Central\User;
use App\Services\Central\RoleService;
use App\Services\Central\TenantService;
use App\Services\Central\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userSvc,
        private readonly RoleService $roleSvc,
        private readonly TenantService $tenantSvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => $this->userSvc->paginate(),
            'roles' => $this->roleSvc->all(),
            'tenants' => $this->tenantSvc->all(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $role = $this->roleSvc->findOrFail($validated['role_id']);
        $isAdmin = $role->slug === 'admin';

        $user = $this->userSvc->create($validated);

        if ($isAdmin && ! empty($validated['tenant_id'])) {
            $this->tenantSvc->assignAdmin($validated['tenant_id'], $user->id);
        }

        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $role = $this->roleSvc->findOrFail($validated['role_id']);

        $this->userSvc->update($user, $validated);

        if ($role->slug === 'admin') {
            $this->tenantSvc->reassignAdmin($user->id, $validated['tenant_id'] ?? null);
        }

        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'No se puede eliminar al Super Admin.');
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'No se puede desactivar al Super Admin.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Estado del usuario actualizado.');
    }
}
