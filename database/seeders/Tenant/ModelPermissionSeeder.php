<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\ModelEntity;
use App\Models\Tenant\ModelPermission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

class ModelPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all()->keyBy('slug');
        $models = ModelEntity::with('permissions')->get()->keyBy('slug');

        $allModels = [
            'companies', 'shops', 'orders', 'contacts', 'accounts',
            'reports', 'declaration', 'sri_scrape', 'employees',
            'models', 'roles', 'users',
        ];

        $adminRole = $roles['admin'] ?? null;

        if (! $adminRole) {
            return;
        }

        // Admin → todos los permisos de todos los módulos
        foreach ($allModels as $modelSlug) {
            $model = $models[$modelSlug] ?? null;
            if (! $model) {
                continue;
            }

            foreach ($model->permissions as $perm) {
                ModelPermission::updateOrCreate([
                    'role_id' => $adminRole->id,
                    'permission_id' => $perm->id,
                    'model_entity_id' => $model->id,
                ]);
            }
        }
    }
}
