<?php

namespace App\Services\Central;

use App\Models\Permission;

class PermissionService
{
    public function all()
    {
        return Permission::all();
    }

    public function allWithCount()
    {
        return Permission::withCount('modelPermissions')->get();
    }

    public function create(array $data): Permission
    {
        return Permission::create($data);
    }

    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);

        return $permission;
    }
}
