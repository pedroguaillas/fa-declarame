<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Role\StoreRoleRequest;
use App\Http\Requests\Central\Role\UpdateRoleRequest;
use App\Models\Central\Role;
use App\Services\Central\ModelEntityService;
use App\Services\Central\PermissionService;
use App\Services\Central\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleSvc,
        private readonly PermissionService $permissionSvc,
        private readonly ModelEntityService $modelEntitySvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Central/Roles/Index', [
            'roles' => $this->roleSvc->allWithCountUser(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Roles/Form', [
            'permissions' => $this->permissionSvc->all(),
            'modelEntities' => $this->modelEntitySvc->all(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roleSvc->create($request->validated());

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): Response
    {
        $role->load(['modelPermissions']);

        return Inertia::render('Central/Roles/Form', [
            'role' => $role,
            'permissions' => $this->permissionSvc->all(),
            'modelEntities' => $this->modelEntitySvc->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roleSvc->update($role, $request->validated());

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'No se puede eliminar un rol con usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }
}
