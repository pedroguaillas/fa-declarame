<?php

namespace App\Http\Controllers;

use App\Models\ModelEntity;
use App\Models\ModelPermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')
            ->with(['modelPermissions.permission', 'modelPermissions.modelEntity'])
            ->get();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Form', [
            'permissions' => Permission::all(),
            'modelEntities' => ModelEntity::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*.permission_id' => 'required|exists:permissions,id',
            'permissions.*.model_entity_id' => 'required|exists:model_entities,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): Response
    {
        $role->load(['modelPermissions']);

        return Inertia::render('Roles/Form', [
            'role' => $role,
            'permissions' => Permission::all(),
            'modelEntities' => ModelEntity::all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles', 'slug')->ignore($role->id)],
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*.permission_id' => 'required|exists:permissions,id',
            'permissions.*.model_entity_id' => 'required|exists:model_entities,id',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->syncPermissions($role, $validated['permissions'] ?? []);

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

    private function syncPermissions(Role $role, array $permissions): void
    {
        $role->modelPermissions()->delete();

        $data = collect($permissions)->map(fn($p) => [
            'role_id' => $role->id,
            'permission_id' => $p['permission_id'],
            'model_entity_id' => $p['model_entity_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        ModelPermission::insert($data);
    }
}