<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\ModelEntity;
use Illuminate\Database\Seeder;

class ModelEntitySeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'name' => 'Empresas',
                'slug' => 'companies',
                'description' => 'Gestión de empresas',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                ],
            ],
            [
                'name' => 'Tiendas',
                'slug' => 'shops',
                'description' => 'Gestión de tiendas',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                ],
            ],
            [
                'name' => 'Pedidos',
                'slug' => 'orders',
                'description' => 'Gestión de pedidos',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                ],
            ],
            [
                'name' => 'Contactos',
                'slug' => 'contacts',
                'description' => 'Gestión de contactos',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                ],
            ],
            [
                'name' => 'Cuentas',
                'slug' => 'accounts',
                'description' => 'Gestión de cuentas contables',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                ],
            ],
            [
                'name' => 'Reportes',
                'slug' => 'reports',
                'description' => 'Gestión de reportes',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Exportar', 'slug' => 'export'],
                ],
            ],
            [
                'name' => 'Declaración',
                'slug' => 'declaration',
                'description' => 'Declaración SRI',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Exportar', 'slug' => 'export'],
                ],
            ],
            [
                'name' => 'SRI Scrape',
                'slug' => 'sri_scrape',
                'description' => 'Extracción de datos SRI',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Ejecutar', 'slug' => 'execute'],
                ],
            ],
            [
                'name' => 'Empleados',
                'slug' => 'employees',
                'description' => 'Gestión de empleados del tenant',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                ],
            ],
            [
                'name' => 'Modelos',
                'slug' => 'models',
                'description' => 'Gestión de modelos del tenant',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                ],
            ],
            [
                'name' => 'Roles',
                'slug' => 'roles',
                'description' => 'Gestión de roles del tenant',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
                    ['name' => 'Asignar', 'slug' => 'assign'],
                ],
            ],
            [
                'name' => 'Usuarios',
                'slug' => 'users',
                'description' => 'Gestión de usuarios del tenant',
                'permissions' => [
                    ['name' => 'Ver', 'slug' => 'view'],
                    ['name' => 'Crear', 'slug' => 'create'],
                    ['name' => 'Editar', 'slug' => 'edit'],
                    ['name' => 'Eliminar', 'slug' => 'delete'],
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
