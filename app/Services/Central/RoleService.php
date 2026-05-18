<?php

namespace App\Services\Central;

use App\Models\Central\Role;

class RoleService
{
    public function all()
    {
        return Role::where('slug', '!=', 'super_admin')->get();
    }

    public function allWithCountUser()
    {
        return Role::withCount('users')
            ->with(['modelPermissions.permission', 'modelPermissions.modelEntity'])
            ->get();
    }

    public function findOrFail(int $id): Role
    {
        return Role::findOrFail($id);
    }

    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return $role;
    }

    public function update(Role $role, array $data): Role
    {
        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return $role;
    }

    private function syncPermissions(Role $role, array $permissions): void
    {
        $role->modelPermissions()->delete();

        $role->modelPermissions()->createMany(
            collect($permissions)->map(fn ($p) => [
                'permission_id' => $p['permission_id'],
                'model_entity_id' => $p['model_entity_id'],
            ])->toArray(),
        );
    }
}
