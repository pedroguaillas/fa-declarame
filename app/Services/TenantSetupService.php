<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Tenant\ContributorType;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\VoucherType;
use Database\Seeders\RetentionSeeder;


class TenantSetupService
{
    public function setup(Tenant $tenant): void
    {
        $voucherTypes = [
            // Comprobantes Principales (Uso Frecuente)
            ['code' => '01', 'initial' => 'FAC', 'description' => 'Factura'],
            ['code' => '02', 'initial' => 'N/V', 'description' => 'Nota de Venta'],
            ['code' => '03', 'initial' => 'L/C', 'description' => 'Liquidación de Compras'],
            ['code' => '04', 'initial' => 'N/C', 'description' => 'Nota de Crédito'],
            ['code' => '05', 'initial' => 'N/D', 'description' => 'Nota de Débito'],
            ['code' => '07', 'initial' => 'RET', 'description' => 'Comprobante de Retención'],
            // Documentos Autorizados y Específicos
            ['code' => '08', 'initial' => 'OTRO', 'description' => 'Boletos o entradas a espectáculos públicos'],
            ['code' => '09', 'initial' => 'OTRO', 'description' => 'Tiquetes emitidos por máquinas registradoras'],
            ['code' => '11', 'initial' => 'OTRO', 'description' => 'Pasajes aéreos'],
            ['code' => '12', 'initial' => 'OTRO', 'description' => 'Documentos emitidos por Instituciones Financieras'],
            ['code' => '15', 'initial' => 'OTRO', 'description' => 'Comprobantes de venta emitidos en el exterior'],
            ['code' => '16', 'initial' => 'OTRO', 'description' => 'Formularios Únicos de Exportación (FUE)'],
            ['code' => '18', 'initial' => 'OTRO', 'description' => 'Documentos autorizados emitidos por instituciones del Estado'],
            ['code' => '19', 'initial' => 'OTRO', 'description' => 'Comprobantes de pago de cuotas o aportes(Condominios)'],
            ['code' => '20', 'initial' => 'OTRO', 'description' => 'Documentos por servicios administrativos emitidos por Organismos Oficiales'],
            ['code' => '21', 'initial' => 'OTRO', 'description' => 'Carta de porte aéreo'],
            ['code' => '22', 'initial' => 'OTRO', 'description' => 'Resumen de ventas de tiquetes aéreos'],
            ['code' => '23', 'initial' => 'OTRO', 'description' => 'Nota de Crédito por boletos aéreos'],
            ['code' => '24', 'initial' => 'OTRO', 'description' => 'Nota de Débito por boletos aéreos'],
            // Casos Especiales y Reembolsos
            ['code' => '41', 'initial' => 'OTRO', 'description' => 'Comprobante de venta emitido por reembolso'],
            ['code' => '42', 'initial' => 'OTRO', 'description' => 'Documento represado'],
            ['code' => '43', 'initial' => 'OTRO', 'description' => 'Liquidación para explotación y exploración de hidrocarburos'],
            ['code' => '45', 'initial' => 'OTRO', 'description' => 'Liquidación por prestación de servicios de transporte'],
            ['code' => '47', 'initial' => 'OTRO', 'description' => 'Nota de Crédito por reembolso'],
            ['code' => '48', 'initial' => 'OTRO', 'description' => 'Nota de Débito por reembolso'],
        ];

        $tenant->run(function () use ($voucherTypes): void {

            ContributorType::insert([
                ['description' => 'GENERAL'],
                ['description' => 'RIMPE EMPRENDEDOR'],
                ['description' => 'RIMPE NEGOCIO POPULAR'],
            ]);

            IdentificationType::insert([
                ['code_order' => '04', 'code_shop' => '01','description' => 'RUC'],
                ['code_order' => '05', 'code_shop' => '02','description' => 'CEDULA'],
                ['code_order' => '06', 'code_shop' => '03','description' => 'PASAPORTE'],
            ]);

            $identificationType = IdentificationType::create([
                'code_order' => '07', 'description' => 'CONSUMIDOR FINAL',
            ]);

            $identificationType->contacts()->create([
                'identification' => '9999999999999',
                'name' => 'CONSUMIDOR FINAL',
            ]);

            VoucherType::insert($voucherTypes);

            (new RetentionSeeder)->run();
        });
    }
}
