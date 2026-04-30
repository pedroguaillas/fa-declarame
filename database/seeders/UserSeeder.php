<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\Tenant\Company;
use App\Models\Tenant\ContributorType;
use App\Models\User;
use App\Services\TenantSetupService;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        $adminRole = Role::where('slug', 'admin')->first();
        $employeeRole = Role::where('slug', 'employee')->first();
        $tenant = Tenant::find('factus');

        // Super Admin — sin tenant
        User::create([
            'email' => 'peter.tufi@gmail.com',
            'username' => 'peters',
            'name' => 'Pedro Guaillas',
            'password' => 'LastDe22*',
            'role_id' => $superAdminRole->id,
            'tenant_id' => null,
            'admin_id' => null,
            'is_active' => true,
        ]);

        // Admin — asignado al tenant demo
        $admin = User::create([
            'email' => 'info@facec.ec',
            'username' => 'declarame',
            'name' => 'Administrador Demo',
            'password' => 'Demo123',
            'role_id' => $adminRole->id,
            'tenant_id' => $tenant?->id,
            'admin_id' => null,
            'is_active' => true,
        ]);

        if ($tenant) {
            $tenant->user_id = $admin->id;
            $tenant->save();
        }

        if ($tenant) {
            app(TenantSetupService::class)->setup($tenant);

            $tenant->run(function (): void {
                Company::create([
                    'ruc' => '1105167694001',
                    'name' => 'DECLARAME',
                    'matrix_address' => 'COLOMBIA',
                    'contributor_type_id' => ContributorType::first()->id,
                ]);
            });
        }
    }
}
