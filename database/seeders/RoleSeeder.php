<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Acceso total al sistema',
            ],
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Administrador con suscripción activa',
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Empleado registrado por un Admin',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}