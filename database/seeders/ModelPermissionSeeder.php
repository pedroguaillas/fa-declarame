<?php

namespace Database\Seeders;

use App\Models\ModelEntity;
use App\Models\ModelPermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ModelPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all()->keyBy('slug');
        $permissions = Permission::all()->keyBy('slug');
        $models = ModelEntity::all()->keyBy('slug');

        $allPermissions = ['view', 'create', 'edit', 'delete', 'assign'];
        $allModels = ['permissions', 'models', 'roles', 'users', 'plans', 'subscriptions'];

        // Super Admin → todo
        foreach ($allModels as $modelSlug) {
            foreach ($allPermissions as $permSlug) {
                ModelPermission::updateOrCreate([
                    'role_id' => $roles['super_admin']->id,
                    'permission_id' => $permissions[$permSlug]->id,
                    'model_entity_id' => $models[$modelSlug]->id,
                ]);
            }
        }
    }
}
