<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'Ver',
                'slug' => 'view',
                'description' => 'Ver registros',
            ],
            [
                'name' => 'Crear',
                'slug' => 'create',
                'description' => 'Crear registros',
            ],
            [
                'name' => 'Editar',
                'slug' => 'edit',
                'description' => 'Editar registros',
            ],
            [
                'name' => 'Eliminar',
                'slug' => 'delete',
                'description' => 'Eliminar registros',
            ],
            [
                'name' => 'Asignar',
                'slug' => 'assign',
                'description' => 'Asignar permisos y roles',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }
    }
}