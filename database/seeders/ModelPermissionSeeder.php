<?php

namespace Database\Seeders;

use App\Models\Central\ModelEntity;
use App\Models\Central\ModelPermission;
use App\Models\Central\Role;
use Illuminate\Database\Seeder;

class ModelPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all()->keyBy('slug');
        $models = ModelEntity::with('permissions')->get()->keyBy('slug');

        $allModels = ['permissions', 'models', 'roles', 'users', 'plans', 'subscriptions'];

        // Super Admin → todos los permisos de todos los módulos
        foreach ($allModels as $modelSlug) {
            $model = $models[$modelSlug] ?? null;
            if (! $model) {
                continue;
            }

            foreach ($model->permissions as $perm) {
                ModelPermission::updateOrCreate([
                    'role_id' => $roles['super_admin']->id,
                    'permission_id' => $perm->id,
                    'model_entity_id' => $model->id,
                ]);
            }
        }
    }
}
