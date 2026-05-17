<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantSetupService;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // generar un tenant por defecto
        $domain = config('app.domain', 'localhost');

        $tenantData = [
            'id' => 'factus',
            'name' => 'Factus S.A',
        ];

        $tenant = Tenant::updateOrCreate(
            ['id' => $tenantData['id']],
            ['name' => $tenantData['name']]
        );

        if (! $tenant->domains()->exists()) {
            $tenant->domains()->create([
                'domain' => $tenantData['id'].'.'.$domain,
            ]);
        }

        // generar el usuario administrador del tenant
        $adminRole = Role::where('slug', 'admin')->first();

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

        $tenant->user_id = $admin->id;
        $tenant->save();

        // inicializar datos iniciales del tenant
        app(TenantSetupService::class)->setup($tenant);
    }
}
