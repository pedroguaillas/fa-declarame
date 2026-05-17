<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            // super administrdor de todo el sistema
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Acceso total al sistema',
            ],
            // administradores a quienes se les vende un plan para el uso del sistema
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrador con suscripción activa',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
