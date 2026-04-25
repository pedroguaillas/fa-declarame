<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $adminRole      = Role::where('slug', 'admin')->first();
        $employeeRole   = Role::where('slug', 'employee')->first();
        $tenant         = Tenant::find('factus');

        // Super Admin — sin tenant
        User::updateOrCreate(
            ['email' => 'abelandrade677@gmail.com'],
            [
                'name'      => 'Abel Andrade',
                'password'  => 'password',
                'role_id'   => $superAdminRole->id,
                'tenant_id' => null,
                'admin_id'  => null,
                'is_active' => true,
            ]
        );

        // Admin — asignado al tenant demo
        $admin = User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'      => 'Administrador',
                'password'  => 'password',
                'role_id'   => $adminRole->id,
                'tenant_id' => $tenant?->id,
                'admin_id'  => null,
                'is_active' => true,
            ]
        );

        // Asignar user_id al tenant
        if ($tenant) {
            $tenant->user_id = $admin->id;
            $tenant->save();
        }

        // Empleado — vive en la DB del tenant
        if ($tenant) {
            tenancy()->initialize($tenant);

            \App\Models\TenantUser::updateOrCreate(
                ['email' => 'empleado@admin.com'],
                [
                    'name'      => 'Empleado Demo',
                    'password'  => 'password',
                    'is_active' => true,
                ]
            );

            tenancy()->end();
        }
    }
}
