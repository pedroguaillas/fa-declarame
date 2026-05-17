<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Permission\StorePermissionRequest;
use App\Http\Requests\Central\Permission\UpdatePermissionRequest;
use App\Models\Central\Permission;
use App\Services\Central\PermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionSvc,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Central/Permissions/Index', [
            'permissions' => $this->permissionSvc->allWithCount(),
        ]);
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        $this->permissionSvc->create($request->validated());

        return back()->with('success', 'Permiso creado correctamente.');
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $this->permissionSvc->update($permission, $request->validated());

        return back()->with('success', 'Permiso actualizado correctamente.');
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($permission->modelPermissions()->exists()) {
            return back()->with('error', 'No se puede eliminar un permiso asignado a roles.');
        }

        $permission->delete();

        return back()->with('success', 'Permiso eliminado correctamente.');
    }
}
