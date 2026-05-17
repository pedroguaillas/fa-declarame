<?php

namespace Database\Seeders;

use App\Models\Central\ModelEntity;
use Illuminate\Database\Seeder;

class ModelEntitySeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'name' => 'Permisos',
                'slug' => 'permissions',
                'description' => 'Gestión de permisos',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view', 'description' => 'Ver registros'],
                    ['name' => 'Asignar', 'slug' => 'assign', 'description' => 'Asignar permisos y roles'],
                ],
            ],
            [
                'name' => 'Modelos',
                'slug' => 'models',
                'description' => 'Gestión de modelos del sistema',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view', 'description' => 'Ver registros'],
                    ['name' => 'Crear', 'slug' => 'create', 'description' => 'Crear registros'],
                    ['name' => 'Editar', 'slug' => 'edit', 'description' => 'Editar registros'],
                    ['name' => 'Eliminar', 'slug' => 'delete', 'description' => 'Eliminar registros'],
                ],
            ],
            [
                'name' => 'Roles',
                'slug' => 'roles',
                'description' => 'Gestión de roles',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view', 'description' => 'Ver registros'],
                    ['name' => 'Crear', 'slug' => 'create', 'description' => 'Crear registros'],
                    ['name' => 'Editar', 'slug' => 'edit', 'description' => 'Editar registros'],
                    ['name' => 'Eliminar', 'slug' => 'delete', 'description' => 'Eliminar registros'],
                    ['name' => 'Asignar', 'slug' => 'assign', 'description' => 'Asignar permisos y roles'],
                ],
            ],
            [
                'name' => 'Usuarios',
                'slug' => 'users',
                'description' => 'Gestión de usuarios',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view', 'description' => 'Ver registros'],
                    ['name' => 'Crear', 'slug' => 'create', 'description' => 'Crear registros'],
                    ['name' => 'Editar', 'slug' => 'edit', 'description' => 'Editar registros'],
                    ['name' => 'Eliminar', 'slug' => 'delete', 'description' => 'Eliminar registros'],
                    ['name' => 'Asignar', 'slug' => 'assign', 'description' => 'Asignar permisos y roles'],
                ],
            ],
            [
                'name' => 'Planes',
                'slug' => 'plans',
                'description' => 'Gestión de planes',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view', 'description' => 'Ver registros'],
                    ['name' => 'Crear', 'slug' => 'create', 'description' => 'Crear registros'],
                    ['name' => 'Editar', 'slug' => 'edit', 'description' => 'Editar registros'],
                    ['name' => 'Eliminar', 'slug' => 'delete', 'description' => 'Eliminar registros'],
                ],
            ],
            [
                'name' => 'Suscripciones',
                'slug' => 'subscriptions',
                'description' => 'Gestión de suscripciones',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view', 'description' => 'Ver registros'],
                    ['name' => 'Crear', 'slug' => 'create', 'description' => 'Crear registros'],
                    ['name' => 'Editar', 'slug' => 'edit', 'description' => 'Editar registros'],
                    ['name' => 'Eliminar', 'slug' => 'delete', 'description' => 'Eliminar registros'],
                ],
            ],
        ];

        foreach ($models as $model) {
            $permissions = $model['permissions'];
            unset($model['permissions']);

            $entity = ModelEntity::updateOrCreate(['slug' => $model['slug']], $model);

            foreach ($permissions as $perm) {
                $entity->permissions()->updateOrCreate(
                    ['slug' => $perm['slug']],
                    $perm,
                );
            }
        }
    }
}
