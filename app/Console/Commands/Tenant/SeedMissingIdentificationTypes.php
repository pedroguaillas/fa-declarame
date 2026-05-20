<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\IdentificationType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tenant:seed-identification-types {--tenants=* : IDs de tenants específicos (opcional)}')]
#[Description('Inserta IdentificationTypes faltantes en tenants existentes')]
class SeedMissingIdentificationTypes extends Command
{
    /**
     * @var array<array{code_order: string, code_shop?: string, description: string}>
     */
    private array $types = [
        ['code_order' => '04', 'code_shop' => '01', 'description' => 'RUC'],
        ['code_order' => '05', 'code_shop' => '02', 'description' => 'CEDULA'],
        ['code_order' => '06', 'code_shop' => '03', 'description' => 'PASAPORTE'],
        ['code_order' => '07', 'description' => 'CONSUMIDOR FINAL'],
        ['code_order' => '08', 'description' => 'IDENTIFICACIÓN DEL EXTERIOR'],
    ];

    public function handle(): int
    {
        $tenantIds = $this->option('tenants');

        $query = Tenant::query();

        if (! empty($tenantIds)) {
            $query->whereIn('id', $tenantIds);
        }

        $tenants = $query->get();

        $this->info("Procesando {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant): void {
                foreach ($this->types as $type) {
                    $existing = IdentificationType::where('description', $type['description'])->first();

                    if ($existing) {
                        $this->line("  [{$tenant->id}] Ya existe: {$type['description']}");

                        continue;
                    }

                    $created = IdentificationType::create($type);

                    $this->info("  [{$tenant->id}] Creado: {$type['description']}");
                }
            });
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
