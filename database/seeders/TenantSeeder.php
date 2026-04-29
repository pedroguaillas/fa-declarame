<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $domain = config('app.domain', 'localhost');

        $tenants = [
            [
                'id'   => 'factus',
                'name' => 'Factus S.A',
            ],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::updateOrCreate(
                ['id' => $tenantData['id']],
                ['name' => $tenantData['name']]
            );

            // Crear dominio si no existe
            if (!$tenant->domains()->exists()) {
                $tenant->domains()->create([
                    'domain' => $tenantData['id'] . '.' . $domain,
                ]);
            }

            // Correr migraciones del tenant
            // tenancy()->initialize($tenant);
            // Artisan::call('tenants:migrate', [
            //     '--tenants' => [$tenant->id],
            // ]);
            // tenancy()->end();
        }
    }
}
