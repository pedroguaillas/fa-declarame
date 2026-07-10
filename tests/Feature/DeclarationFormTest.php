<?php

namespace Tests\Feature;

use App\Services\F103FormService;
use App\Services\F104FormService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifica los casilleros de F103/F104 contra valores calculados a mano:
 * mapeo de códigos AIR, bruto vs NETO con notas de crédito, segmentación
 * por sustento tributario, RIMPE NP y retenciones IVA por porcentaje.
 */
class DeclarationFormTest extends TestCase
{
    private const COMPANY_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedFixture();
    }

    /** @return array<string, float|int|string|null> */
    private function flatten(array $form): array
    {
        $values = [];

        foreach ($form['sections'] as $section) {
            foreach ($section['rows'] as $row) {
                $values[$row['c']] = $row['v'];
            }
        }

        return $values;
    }

    public function test_f103_maps_air_codes_to_casilleros(): void
    {
        $form = app(F103FormService::class)->build(self::COMPANY_ID, 2026, 1, 1);
        $v = $this->flatten($form);

        $this->assertSame(100.0, $v['303']);
        $this->assertSame(10.0, $v['353']);
        $this->assertSame(100.0, $v['349']);
        $this->assertSame(10.0, $v['399']);
        $this->assertSame(10.0, $v['499']);
        $this->assertSame(10.0, $v['905']);
    }

    public function test_f103_reports_unmapped_codes(): void
    {
        $form = app(F103FormService::class)->build(self::COMPANY_ID, 2026, 1, 1);

        $this->assertSame([
            ['code' => 'XYZ', 'base' => 20.0, 'value' => 1.0],
        ], $form['unmapped']);
    }

    public function test_f104_ventas_bruto_excludes_credit_notes_but_neto_subtracts_them(): void
    {
        $v = $this->flatten(app(F104FormService::class)->build(self::COMPANY_ID, 2026, 1, 1));

        $this->assertSame(300.0, $v['401']);
        $this->assertSame(200.0, $v['411']);
        $this->assertSame(5.0, $v['403']);
        $this->assertSame(30.0, $v['421']);
        $this->assertSame(305.0, $v['409']);
        $this->assertSame(205.0, $v['419']);
        $this->assertSame(30.0, $v['482']);
        $this->assertSame(1.0, $v['431']);
    }

    public function test_f104_compras_segmented_by_tax_support(): void
    {
        $v = $this->flatten(app(F104FormService::class)->build(self::COMPANY_ID, 2026, 1, 1));

        $this->assertSame(100.0, $v['500'], 'con derecho a crédito (sustento 01)');
        $this->assertSame(200.0, $v['501'], 'activo fijo con derecho (sustento 03)');
        $this->assertSame(50.0, $v['502'], 'sin derecho (sustento 02)');
        $this->assertSame(10.0, $v['507'], 'tarifa 0%');
        $this->assertSame(40.0, $v['508'], 'RIMPE Negocio Popular');
        $this->assertSame(20.0, $v['540'], 'tarifa 5%');
        $this->assertSame(70.0, $v['510'], 'NETO con derecho tras nota de crédito');
        $this->assertSame(10.5, $v['520']);
        $this->assertSame(30.0, $v['521']);
        $this->assertSame(7.5, $v['522']);
        $this->assertSame(1.0, $v['560']);
        $this->assertSame(420.0, $v['509']);
        $this->assertSame(390.0, $v['519']);
        $this->assertSame(49.0, $v['529']);
        $this->assertSame(2.0, $v['531']);
        $this->assertSame(3.0, $v['532']);
    }

    public function test_f104_summary_and_iva_retentions(): void
    {
        $v = $this->flatten(app(F104FormService::class)->build(self::COMPANY_ID, 2026, 1, 1));

        $this->assertSame(0.9756, $v['563']);
        $this->assertSame(40.49, $v['564']);
        $this->assertSame(0.0, $v['601']);
        $this->assertSame(10.49, $v['602']);
        $this->assertSame(9.0, $v['609'], 'solo retenciones IVA recibidas, no RENTA');
        $this->assertSame(9.0, $v['617']);
        $this->assertSame(0.0, $v['620']);
        $this->assertSame(4.5, $v['725'], 'retención IVA 30%');
        $this->assertSame(7.5, $v['731'], 'retención IVA 100%');
        $this->assertSame(12.0, $v['799']);
        $this->assertSame(12.0, $v['859']);
    }

    private function createSchema(): void
    {
        foreach (['order_retention_items', 'shop_retention_items', 'retentions', 'shops', 'orders', 'contacts', 'contributor_types', 'tax_supports', 'voucher_types', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ruc');
            $table->string('name');
            $table->string('type_declaration')->nullable();
        });

        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
        });

        Schema::create('tax_supports', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('description');
        });

        Schema::create('contributor_types', function (Blueprint $table) {
            $table->id();
            $table->string('description');
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('identification');
            $table->string('name');
            $table->unsignedBigInteger('contributor_type_id')->nullable();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('voucher_type_id');
            $table->unsignedBigInteger('tax_support_id')->nullable();
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
            ['id' => 1, 'ruc' => '1105167694001', 'name' => 'EMPRESA UNO', 'type_declaration' => 'mensual'],
        ]);

        DB::table('voucher_types')->insert([
            ['id' => 1, 'code' => '01', 'description' => 'FACTURA'],
            ['id' => 2, 'code' => '04', 'description' => 'NOTA DE CREDITO'],
        ]);

        DB::table('tax_supports')->insert([
            ['id' => 1, 'code' => '01', 'description' => 'Crédito IVA'],
            ['id' => 2, 'code' => '02', 'description' => 'Costo o gasto'],
            ['id' => 3, 'code' => '03', 'description' => 'Activo fijo crédito IVA'],
        ]);

        DB::table('contributor_types')->insert([
            ['id' => 1, 'description' => 'GENERAL'],
            ['id' => 2, 'description' => 'RIMPE NEGOCIO POPULAR'],
        ]);

        DB::table('contacts')->insert([
            ['id' => 1, 'identification' => '0912345678001', 'name' => 'PROVEEDOR GENERAL', 'contributor_type_id' => 1],
            ['id' => 2, 'identification' => '0998765432001', 'name' => 'NEGOCIO POPULAR', 'contributor_type_id' => 2],
        ]);

        $shop = fn (array $overrides): array => array_merge([
            'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 1, 'tax_support_id' => 1,
            'state' => 'AUTORIZADO', 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0,
            'base8' => 0, 'base15' => 0, 'iva5' => 0, 'iva8' => 0, 'iva15' => 0, 'total' => 0,
        ], $overrides);

        DB::table('shops')->insert([
            // Con derecho a crédito (sustento 01)
            $shop(['id' => 1, 'emision' => '2026-01-05', 'no_iva' => 2, 'exempt' => 3, 'base0' => 10, 'base5' => 20, 'base15' => 100, 'iva5' => 1, 'iva15' => 15, 'total' => 151]),
            // Sin derecho (sustento 02)
            $shop(['id' => 2, 'emision' => '2026-01-08', 'tax_support_id' => 2, 'base15' => 50, 'iva15' => 7.5, 'total' => 57.5]),
            // Activo fijo con derecho (sustento 03)
            $shop(['id' => 3, 'emision' => '2026-01-10', 'tax_support_id' => 3, 'base15' => 200, 'iva15' => 30, 'total' => 230]),
            // Nota de crédito sobre compra con derecho: resta solo en NETO
            $shop(['id' => 4, 'emision' => '2026-01-12', 'voucher_type_id' => 2, 'base15' => 30, 'iva15' => 4.5, 'total' => 34.5]),
            // Proveedor RIMPE Negocio Popular (nota de venta 0%)
            $shop(['id' => 5, 'emision' => '2026-01-15', 'contact_id' => 2, 'tax_support_id' => null, 'base0' => 40, 'total' => 40]),
            // No autorizada: excluida
            $shop(['id' => 6, 'emision' => '2026-01-20', 'state' => 'PENDIENTE', 'base15' => 999, 'iva15' => 149.85, 'total' => 1148.85]),
        ]);

        $order = fn (array $overrides): array => array_merge([
            'company_id' => 1, 'contact_id' => 1, 'voucher_type_id' => 1,
            'state' => 'AUTORIZADO', 'no_iva' => 0, 'exempt' => 0, 'base0' => 0, 'base5' => 0,
            'base15' => 0, 'iva5' => 0, 'iva15' => 0, 'total' => 0,
        ], $overrides);

        DB::table('orders')->insert([
            $order(['id' => 1, 'emision' => '2026-01-06', 'no_iva' => 1, 'base0' => 5, 'base15' => 300, 'iva15' => 45, 'total' => 351]),
            // Nota de crédito de venta
            $order(['id' => 2, 'emision' => '2026-01-09', 'voucher_type_id' => 2, 'base15' => 100, 'iva15' => 15, 'total' => 115]),
            // Anulada: excluida
            $order(['id' => 3, 'emision' => '2026-01-18', 'state' => 'ANULADO', 'base15' => 500, 'iva15' => 75, 'total' => 575]),
        ]);

        DB::table('retentions')->insert([
            ['id' => 1, 'code' => '303', 'description' => 'Honorarios profesionales', 'percentage' => 10, 'type' => 'RENTA'],
            ['id' => 2, 'code' => 'XYZ', 'description' => 'Código desconocido', 'percentage' => 5, 'type' => 'RENTA'],
            ['id' => 3, 'code' => '1', 'description' => '30% del IVA causado', 'percentage' => 30, 'type' => 'IVA'],
            ['id' => 4, 'code' => '3', 'description' => '100% del IVA causado', 'percentage' => 100, 'type' => 'IVA'],
        ]);

        DB::table('shop_retention_items')->insert([
            ['id' => 1, 'shop_id' => 1, 'retention_id' => 1, 'base' => 100, 'percentage' => 10, 'value' => 10],
            ['id' => 2, 'shop_id' => 1, 'retention_id' => 2, 'base' => 20, 'percentage' => 5, 'value' => 1],
            ['id' => 3, 'shop_id' => 1, 'retention_id' => 3, 'base' => 15, 'percentage' => 30, 'value' => 4.5],
            ['id' => 4, 'shop_id' => 2, 'retention_id' => 4, 'base' => 7.5, 'percentage' => 100, 'value' => 7.5],
        ]);

        DB::table('order_retention_items')->insert([
            ['id' => 1, 'order_id' => 1, 'retention_id' => 3, 'base' => 30, 'percentage' => 30, 'value' => 9],
            // Retención RENTA recibida: no cuenta en casillero 609 (solo IVA)
            ['id' => 2, 'order_id' => 1, 'retention_id' => 1, 'base' => 300, 'percentage' => 1, 'value' => 3],
        ]);
    }
}
