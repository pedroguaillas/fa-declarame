<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Permissions/Index', [
            'permissions' => Permission::withCount('modelPermissions')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug',
            'description' => 'nullable|string|max:500',
        ]);

        Permission::create($validated);

        return back()->with('success', 'Permiso creado correctamente.');
    }

    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('permissions', 'slug')->ignore($permission->id)],
            'description' => 'nullable|string|max:500',
        ]);

        $permission->update($validated);

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
