<?php

namespace Tests\Feature;

use App\Models\Tenant\Company;
use App\Services\AtsXmlService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Una orden con varias líneas de retención (IVA + RENTA) no debe duplicarse
 * en el ATS: antes del fix, el join a order_retention_items ocurría antes
 * del GROUP BY/SUM, así que una factura con 2 líneas de retención se contaba
 * 2 veces (numeroComprobantes, baseImpGrav y montoIva duplicados).
 */
class AtsXmlServiceRetentionFanoutTest extends TestCase
{
    private const COMPANY_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedFixture();

        session(['current_company_id' => self::COMPANY_ID]);
    }

    private function createSchema(): void
    {
        foreach (['order_retention_items', 'retentions', 'orders', 'shops', 'contacts', 'identification_types', 'voucher_types', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ruc');
            $table->string('name');
        });

        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
        });

        Schema::create('identification_types', function (Blueprint $table) {
            $table->id();
            $table->string('code_order');
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('identification_type_id');
            $table->string('identification');
            $table->string('name');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('voucher_type_id');
            $table->date('emision');
            $table->string('state');
            foreach (['no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15'] as $column) {
                $table->decimal($column, 12, 2)->default(0);
            }
        });

        // Solo se necesita para que la consulta de compras (Shop::with(...)) no falle; se deja vacía.
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->date('emision');
            $table->string('state');
        });

        Schema::create('retentions', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
            $table->decimal('percentage', 5, 2);
            $table->string('type');
        });

        Schema::create('order_retention_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('retention_id');
            $table->decimal('base', 12, 2);
            $table->decimal('percentage', 5, 2);
            $table->decimal('value', 12, 2);
        });
    }

    private function seedFixture(): void
    {
        DB::table('companies')->insert([
            ['id' => 1, 'ruc' => '0791840363001', 'name' => 'EMPRESA UNO'],
        ]);

        DB::table('voucher_types')->insert([
            ['id' => 1, 'code' => '01', 'description' => 'FACTURA'],
            ['id' => 2, 'code' => '04', 'description' => 'NOTA DE CREDITO'],
        ]);

        DB::table('identification_types')->insert([
            ['id' => 1, 'code_order' => '04'],
        ]);

        DB::table('contacts')->insert([
            ['id' => 1, 'identification_type_id' => 1, 'identification' => '0993152161001', 'name' => 'TUTI'],
        ]);

        DB::table('orders')->insert([
            // Factura con 2 líneas de retención (IVA + RENTA): antes del fix se contaba doble.
            ['id' => 1, 'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 1, 'emision' => '2026-07-05', 'state' => 'AUTORIZADO', 'base15' => 100, 'iva15' => 15],
            // Factura sin retención: no debe alterar el conteo/agregado de la anterior.
            ['id' => 2, 'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 1, 'emision' => '2026-07-10', 'state' => 'AUTORIZADO', 'base15' => 50, 'iva15' => 7.5],
        ]);

        DB::table('retentions')->insert([
            ['id' => 1, 'code' => '303', 'description' => 'RETENCION RENTA', 'percentage' => 10, 'type' => 'RENTA'],
            ['id' => 2, 'code' => '721', 'description' => 'RETENCION IVA', 'percentage' => 30, 'type' => 'IVA'],
        ]);

        DB::table('order_retention_items')->insert([
            ['id' => 1, 'order_id' => 1, 'retention_id' => 1, 'base' => 100, 'percentage' => 10, 'value' => 6],
            ['id' => 2, 'order_id' => 1, 'retention_id' => 2, 'base' => 15, 'percentage' => 30, 'value' => 9],
        ]);
    }

    public function test_order_with_multiple_retention_lines_is_not_duplicated(): void
    {
        $xml = (new AtsXmlService)->generate(Company::find(self::COMPANY_ID), 2026, 7);

        $ventas = new SimpleXMLElement($xml);
        $detalles = $ventas->xpath('//detalleVentas');

        $this->assertCount(1, $detalles, 'las 2 facturas del mismo cliente/tipo deben agruparse en 1 sola línea de detalleVentas');

        $detalle = $detalles[0];

        // 2 facturas (150 base, 22.5 iva en total), no 4 por el fan-out del join.
        $this->assertSame('2', (string) $detalle->numeroComprobantes);
        $this->assertSame('150.00', (string) $detalle->baseImpGrav);
        $this->assertSame('22.50', (string) $detalle->montoIva);

        // Retenciones de la única orden que las tiene, sumadas una sola vez.
        $this->assertSame('9.00', (string) $detalle->valorRetIva);
        $this->assertSame('6.00', (string) $detalle->valorRetRenta);
    }
}
