<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Role\StoreRoleRequest;
use App\Http\Requests\Tenant\Role\UpdateRoleRequest;
use App\Models\Tenant\Role;
use App\Services\Tenant\ModelEntityService;
use App\Services\Tenant\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleSvc,
        private readonly ModelEntityService $modelEntitySvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Tenant/Roles/Index', [
            'roles' => $this->roleSvc->allWithCountUser(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Roles/Form', [
            'modelEntities' => $this->modelEntitySvc->allWithPermissions(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->roleSvc->create($request->validated());

        return redirect()->route('tenant.roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): Response
    {
        $role->load(['modelPermissions']);

        return Inertia::render('Tenant/Roles/Form', [
            'role' => $role,
            'modelEntities' => $this->modelEntitySvc->allWithPermissions(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roleSvc->update($role, $request->validated());

        return redirect()->route('tenant.roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'No se puede eliminar un rol con usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('tenant.roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }
}
