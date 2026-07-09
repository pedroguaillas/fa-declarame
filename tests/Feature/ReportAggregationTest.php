<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\DeclarationController;
use App\Http\Controllers\Tenant\ReportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Verifica la agregación SQL de los reportes de compras/ventas contra
 * valores esperados calculados a mano: signo de nota de crédito (04),
 * buckets por tarifa, retenciones sin signo, filtros de estado/fecha/empresa.
 */
class ReportAggregationTest extends TestCase
{
    private const COMPANY_ID = 1;

    private const FILTERS = ['start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'only_authorized' => true];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedFixture();

        // CompanyScope (scope global de Shop/Order) resuelve la empresa desde la sesión
        session(['current_company_id' => self::COMPANY_ID]);
    }

    private function createSchema(): void
    {
        foreach (['order_retention_items', 'shop_retention_items', 'retentions', 'shop_items', 'product_accounts', 'accounts', 'orders', 'shops', 'contacts', 'voucher_types', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('identification');
            $table->string('name');
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('voucher_type_id');
            $table->date('emision');
            $table->string('state');
            foreach (['sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'total'] as $column) {
                $table->decimal($column, 12, 2)->default(0);
            }
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('voucher_type_id');
            $table->date('emision');
            $table->string('state');
            foreach (['sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base12', 'base15', 'iva5', 'iva12', 'iva15', 'total'] as $column) {
                $table->decimal($column, 12, 2)->default(0);
            }
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
        });

        Schema::create('product_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('company_id');
        });

        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('total', 12, 2);
            $table->decimal('tax_value', 12, 2);
            $table->decimal('tax_percentage', 5, 2);
        });

        Schema::create('retentions', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
            $table->decimal('percentage', 5, 2);
            $table->string('type');
        });

        Schema::create('shop_retention_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('retention_id');
            $table->decimal('base', 12, 2);
            $table->decimal('percentage', 5, 2);
            $table->decimal('value', 12, 2);
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
            ['id' => 1, 'name' => 'EMPRESA UNO'],
        ]);

        DB::table('voucher_types')->insert([
            ['id' => 1, 'code' => '01', 'description' => 'FACTURA'],
            ['id' => 2, 'code' => '04', 'description' => 'NOTA DE CREDITO'],
        ]);

        DB::table('contacts')->insert([
            ['id' => 1, 'identification' => '0912345678', 'name' => 'CLIENTE A'],
            ['id' => 2, 'identification' => '0998765432', 'name' => 'CLIENTE B'],
        ]);

        DB::table('shops')->insert([
            // Factura autorizada dentro del rango
            ['id' => 1, 'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 1, 'emision' => '2026-03-05', 'state' => 'AUTORIZADO', 'sub_total' => 100, 'no_iva' => 1, 'exempt' => 2, 'base0' => 10, 'base5' => 20, 'base8' => 5, 'base12' => 30, 'base15' => 40, 'iva5' => 1, 'iva8' => 0.4, 'iva12' => 3.6, 'iva15' => 6, 'total' => 111],
            // Nota de crédito autorizada (resta)
            ['id' => 2, 'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 2, 'emision' => '2026-03-10', 'state' => 'AUTORIZADO', 'sub_total' => 50, 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0, 'base8' => 0, 'base12' => 0, 'base15' => 50, 'iva5' => 0, 'iva8' => 0, 'iva12' => 0, 'iva15' => 7.5, 'total' => 57.5],
            // No autorizada: excluida con only_authorized
            ['id' => 3, 'company_id' => 1, 'contact_id' => 2, 'voucher_type_id' => 1, 'emision' => '2026-03-15', 'state' => 'PENDIENTE', 'sub_total' => 200, 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0, 'base8' => 0, 'base12' => 0, 'base15' => 200, 'iva5' => 0, 'iva8' => 0, 'iva12' => 0, 'iva15' => 30, 'total' => 230],
            // Otra empresa: excluida
            ['id' => 4, 'company_id' => 2, 'contact_id' => 2, 'voucher_type_id' => 1, 'emision' => '2026-03-15', 'state' => 'AUTORIZADO', 'sub_total' => 300, 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0, 'base8' => 0, 'base12' => 0, 'base15' => 300, 'iva5' => 0, 'iva8' => 0, 'iva12' => 0, 'iva15' => 45, 'total' => 345],
            // Fuera del rango de fechas: excluida
            ['id' => 5, 'company_id' => 1, 'contact_id' => 2, 'voucher_type_id' => 1, 'emision' => '2026-04-02', 'state' => 'AUTORIZADO', 'sub_total' => 999, 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0, 'base8' => 0, 'base12' => 0, 'base15' => 999, 'iva5' => 0, 'iva8' => 0, 'iva12' => 0, 'iva15' => 149.85, 'total' => 1148.85],
        ]);

        DB::table('orders')->insert([
            ['id' => 1, 'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 1, 'emision' => '2026-03-05', 'state' => 'AUTORIZADO', 'sub_total' => 300, 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0, 'base12' => 100, 'base15' => 200, 'iva5' => 0, 'iva12' => 12, 'iva15' => 30, 'total' => 342],
            ['id' => 2, 'company_id' => 1, 'contact_id' => 2, 'voucher_type_id' => 2, 'emision' => '2026-03-08', 'state' => 'AUTORIZADO', 'sub_total' => 80, 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0, 'base12' => 0, 'base15' => 80, 'iva5' => 0, 'iva12' => 0, 'iva15' => 12, 'total' => 92],
            // Anulada: excluida con only_authorized, pero cuenta en ventasSummary (sin filtro de estado)
            ['id' => 3, 'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 1, 'emision' => '2026-03-20', 'state' => 'ANULADO', 'sub_total' => 500, 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0, 'base12' => 500, 'base15' => 0, 'iva5' => 0, 'iva12' => 60, 'iva15' => 0, 'total' => 560],
        ]);

        DB::table('accounts')->insert([
            ['id' => 1, 'code' => '5.1.1', 'name' => 'GASTOS'],
        ]);

        DB::table('product_accounts')->insert([
            ['id' => 1, 'product_id' => 10, 'account_id' => 1, 'company_id' => 1],
        ]);

        DB::table('shop_items')->insert([
            ['id' => 1, 'shop_id' => 1, 'product_id' => 10, 'total' => 100, 'tax_value' => 15, 'tax_percentage' => 15],
            ['id' => 2, 'shop_id' => 1, 'product_id' => 10, 'total' => 20, 'tax_value' => 1, 'tax_percentage' => 5],
            // Item de la nota de crédito: resta
            ['id' => 3, 'shop_id' => 2, 'product_id' => 10, 'total' => 50, 'tax_value' => 7.5, 'tax_percentage' => 15],
            // Producto sin cuenta asignada: excluido por el join
            ['id' => 4, 'shop_id' => 1, 'product_id' => 99, 'total' => 70, 'tax_value' => 10.5, 'tax_percentage' => 15],
            // Compra no autorizada: excluida
            ['id' => 5, 'shop_id' => 3, 'product_id' => 10, 'total' => 200, 'tax_value' => 30, 'tax_percentage' => 15],
            // Total cero: excluido por total > 0
            ['id' => 6, 'shop_id' => 1, 'product_id' => 10, 'total' => 0, 'tax_value' => 0, 'tax_percentage' => 0],
        ]);

        DB::table('retentions')->insert([
            ['id' => 1, 'code' => '303', 'description' => 'RETENCION RENTA', 'percentage' => 10, 'type' => 'RENTA'],
            ['id' => 2, 'code' => '721', 'description' => 'RETENCION IVA', 'percentage' => 30, 'type' => 'IVA'],
        ]);

        DB::table('shop_retention_items')->insert([
            ['id' => 1, 'shop_id' => 1, 'retention_id' => 1, 'base' => 100, 'percentage' => 10, 'value' => 10],
            // IVA: excluida del reporte por retención, pero suma en total_retention
            ['id' => 2, 'shop_id' => 1, 'retention_id' => 2, 'base' => 12, 'percentage' => 30, 'value' => 3.6],
            ['id' => 3, 'shop_id' => 2, 'retention_id' => 1, 'base' => 50, 'percentage' => 10, 'value' => 5],
        ]);

        DB::table('order_retention_items')->insert([
            ['id' => 1, 'order_id' => 1, 'retention_id' => 1, 'base' => 300, 'percentage' => 2, 'value' => 6],
            ['id' => 2, 'order_id' => 1, 'retention_id' => 2, 'base' => 42, 'percentage' => 30, 'value' => 9],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function invokeReport(string $method, array $filters = self::FILTERS): array
    {
        $reflection = new ReflectionMethod(ReportController::class, $method);

        return $reflection->invoke(new ReportController, self::COMPANY_ID, $filters)->toArray();
    }

    /** @return array<string, mixed> */
    private function invokeSummary(string $method): array
    {
        $reflection = new ReflectionMethod(DeclarationController::class, $method);

        return $reflection->invoke(new DeclarationController, self::COMPANY_ID, 2026, 3, 3);
    }

    public function test_shops_by_voucher_type_signs_credit_notes_and_keeps_retentions_unsigned(): void
    {
        $rows = $this->invokeReport('shopsByVoucherTypeRows');

        $this->assertSame([
            [
                'code' => '01', 'description' => 'FACTURA', 'count' => 1,
                'subtotal' => 100.0, 'no_iva' => 1.0, 'exempt' => 2.0,
                'base0' => 10.0, 'base5' => 20.0, 'base8' => 5.0, 'base12' => 30.0, 'base15' => 40.0,
                'iva5' => 1.0, 'iva8' => 0.4, 'iva12' => 3.6, 'iva15' => 6.0,
                'total' => 111.0, 'retentions' => 13.6, 'a_pagar' => 97.4,
            ],
            [
                'code' => '04', 'description' => 'NOTA DE CREDITO', 'count' => 1,
                'subtotal' => -50.0, 'no_iva' => -0.0, 'exempt' => -0.0,
                'base0' => -0.0, 'base5' => -0.0, 'base8' => -0.0, 'base12' => -0.0, 'base15' => -50.0,
                'iva5' => -0.0, 'iva8' => -0.0, 'iva12' => -0.0, 'iva15' => -7.5,
                'total' => -57.5, 'retentions' => 5.0, 'a_pagar' => -62.5,
            ],
        ], $rows);
    }

    public function test_shops_by_provider_groups_by_contact(): void
    {
        $rows = $this->invokeReport('shopsByProviderRows');

        $this->assertSame([
            [
                'identification' => '0912345678', 'name' => 'CLIENTE A',
                'subtotal' => 50.0, 'iva' => 3.5, 'no_iva' => 1.0, 'exempt' => 2.0,
                'base0' => 10.0, 'base5' => 20.0, 'base8' => 5.0, 'base12' => 30.0, 'base15' => -10.0,
                'iva5' => 1.0, 'iva8' => 0.4, 'iva12' => 3.6, 'iva15' => -1.5,
                'total' => 53.5, 'retentions' => 18.6, 'a_pagar' => 34.9,
            ],
        ], $rows);
    }

    public function test_shops_by_account_buckets_by_tax_percentage(): void
    {
        $rows = $this->invokeReport('shopsByAccountRows');

        $this->assertSame([
            [
                'account_code' => '5.1.1', 'account_name' => 'GASTOS',
                'subtotal' => 70.0,
                'base0' => 0.0, 'base5' => 20.0, 'base8' => 0.0, 'base12' => 0.0, 'base15' => 50.0,
                'iva5' => 1.0, 'iva8' => 0.0, 'iva12' => 0.0, 'iva15' => 7.5,
                'iva' => 8.5, 'total' => 78.5,
            ],
        ], $rows);
    }

    public function test_orders_by_voucher_type(): void
    {
        $rows = $this->invokeReport('ordersByVoucherTypeRows');

        $this->assertSame([
            [
                'code' => '01', 'description' => 'FACTURA', 'count' => 1,
                'subtotal' => 300.0, 'iva' => 42.0, 'total' => 342.0,
                'retentions' => 15.0, 'a_cobrar' => 327.0,
            ],
            [
                'code' => '04', 'description' => 'NOTA DE CREDITO', 'count' => 1,
                'subtotal' => -80.0, 'iva' => -12.0, 'total' => -92.0,
                'retentions' => 0.0, 'a_cobrar' => -92.0,
            ],
        ], $rows);
    }

    public function test_orders_by_client(): void
    {
        $rows = $this->invokeReport('ordersByClientRows');

        $this->assertSame([
            [
                'identification' => '0912345678', 'name' => 'CLIENTE A',
                'subtotal' => 300.0, 'iva' => 42.0, 'no_iva' => 0.0, 'exempt' => 0.0,
                'base0' => 0.0, 'base5' => 0.0, 'base12' => 100.0, 'base15' => 200.0,
                'iva5' => 0.0, 'iva12' => 12.0, 'iva15' => 30.0,
                'total' => 342.0, 'retentions' => 15.0, 'a_cobrar' => 327.0,
            ],
            [
                'identification' => '0998765432', 'name' => 'CLIENTE B',
                'subtotal' => -80.0, 'iva' => -12.0, 'no_iva' => -0.0, 'exempt' => -0.0,
                'base0' => -0.0, 'base5' => -0.0, 'base12' => -0.0, 'base15' => -80.0,
                'iva5' => -0.0, 'iva12' => -0.0, 'iva15' => -12.0,
                'total' => -92.0, 'retentions' => 0.0, 'a_cobrar' => -92.0,
            ],
        ], $rows);
    }

    public function test_retention_reports_filter_renta_type_only(): void
    {
        $shopRows = $this->invokeReport('shopsByRetentionRows');
        $orderRows = $this->invokeReport('ordersByRetentionRows');

        $this->assertSame([
            ['code' => '303', 'description' => 'RETENCION RENTA', 'percentage' => 10.0, 'base' => 150.0, 'value' => 15.0],
        ], $shopRows);

        $this->assertSame([
            ['code' => '303', 'description' => 'RETENCION RENTA', 'percentage' => 10.0, 'base' => 300.0, 'value' => 6.0],
        ], $orderRows);
    }

    public function test_compras_summary_filters_authorized_state(): void
    {
        $this->assertSame([
            'count' => 2,
            'subtotal' => 50.0,
            'iva' => 3.5,
            'total' => 53.5,
            'retentions' => 18.6,
            'a_pagar' => 34.9,
        ], $this->invokeSummary('comprasSummary'));
    }

    public function test_ventas_summary_includes_all_states(): void
    {
        $this->assertSame([
            'count' => 3,
            'subtotal' => 720.0,
            'iva' => 90.0,
            'total' => 810.0,
            'retentions' => 15.0,
            'a_cobrar' => 795.0,
        ], $this->invokeSummary('ventasSummary'));
    }

    public function test_only_authorized_false_includes_pending_documents(): void
    {
        $rows = $this->invokeReport('shopsByVoucherTypeRows', array_merge(self::FILTERS, ['only_authorized' => false]));

        $factura = collect($rows)->firstWhere('code', '01');

        $this->assertSame(2, $factura['count']);
        $this->assertSame(300.0, $factura['subtotal']);
    }
}
