<?php

namespace Tests\Feature;

use App\Models\Tenant\Contact;
use App\Models\Tenant\Scopes\CompanyScope;
use App\Models\Tenant\Shop;
use App\Services\ShopImportService;
use App\Services\SriSoapService;
use App\Services\SriXmlParserService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifica que una Liquidación de Compra (codDoc 03) se importe invirtiendo
 * correctamente los roles emisor/comprador: en este documento el RUC de
 * infoTributaria es la propia empresa (compradora) y el "proveedor" del
 * nodo infoLiquidacionCompra es la contraparte (puede tener cédula, no RUC).
 */
class ShopLiquidacionCompraImportTest extends TestCase
{
    private const COMPANY_ID = 1;

    private const COMPANY_RUC = '1307305043001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->seedFixture();

        session(['current_company_id' => self::COMPANY_ID]);
    }

    private function createSchema(): void
    {
        foreach (['shop_items', 'shops', 'products', 'contacts', 'companies', 'voucher_types', 'tax_supports', 'identification_types', 'contributor_types'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('contributor_types', function (Blueprint $table) {
            $table->id();
            $table->string('description')->unique();
        });

        Schema::create('identification_types', function (Blueprint $table) {
            $table->id();
            $table->string('code_order');
            $table->string('code_shop')->nullable();
            $table->string('description');
        });

        Schema::create('tax_supports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('description', 300);
        });

        Schema::create('voucher_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('initial');
            $table->string('description');
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 13)->unique();
            $table->string('name', 300);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('identification_type_id');
            $table->string('identification', 13)->unique();
            $table->string('name', 300);
            $table->string('provider_type', 2)->default('01');
            $table->unsignedBigInteger('contributor_type_id')->nullable();
            $table->json('data_additional')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->string('code', 50);
            $table->string('aux_code', 50)->nullable();
            $table->string('description', 300);
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('voucher_type_id');
            $table->unsignedBigInteger('tax_support_id');
            $table->date('emision');
            $table->string('autorization', 49);
            $table->timestamp('autorized_at')->nullable();
            $table->string('serie', 17);
            foreach (['sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'discount', 'total'] as $column) {
                $table->decimal($column)->default(0);
            }
            $table->string('state');
            $table->unsignedSmallInteger('est_modify')->nullable();
            $table->unsignedSmallInteger('poi_modify')->nullable();
            $table->unsignedInteger('sec_modify')->nullable();
            $table->json('data_additional')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 14, 6);
            $table->decimal('unit_price', 14, 6);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_value', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    private function seedFixture(): void
    {
        \DB::table('contributor_types')->insert([
            ['id' => 1, 'description' => 'GENERAL'],
            ['id' => 2, 'description' => 'RIMPE EMPRENDEDOR'],
            ['id' => 3, 'description' => 'RIMPE NEGOCIO POPULAR'],
        ]);

        \DB::table('identification_types')->insert([
            ['id' => 1, 'code_order' => '04', 'code_shop' => '01', 'description' => 'RUC'],
            ['id' => 2, 'code_order' => '05', 'code_shop' => '02', 'description' => 'CEDULA'],
            ['id' => 3, 'code_order' => '06', 'code_shop' => '03', 'description' => 'PASAPORTE'],
        ]);

        \DB::table('tax_supports')->insert([
            ['id' => 1, 'code' => '01', 'description' => 'Crédito Tributario IVA'],
        ]);

        \DB::table('voucher_types')->insert([
            ['id' => 1, 'code' => '03', 'initial' => 'L/C', 'description' => 'Liquidación de Compras'],
        ]);

        \DB::table('companies')->insert([
            ['id' => self::COMPANY_ID, 'ruc' => self::COMPANY_RUC, 'name' => 'RODRIGUEZ ARANA ANA MARIA'],
        ]);
    }

    public function test_imports_liquidacion_compra_with_provider_as_contact(): void
    {
        $xml = file_get_contents(__DIR__.'/../Fixtures/sri/liquidacion_compra.xml');

        $service = new ShopImportService(new SriSoapService, new SriXmlParserService);

        $result = $service->importFromXml($xml, self::COMPANY_ID, self::COMPANY_RUC);

        $this->assertSame(['imported' => 1, 'skipped' => 0], $result);

        $shop = Shop::withoutGlobalScope(CompanyScope::class)->sole();

        $this->assertSame(self::COMPANY_ID, $shop->company_id);
        $this->assertSame('0909202603130730504300120011000000000173728836510', $shop->autorization);
        $this->assertSame('001-100-000000017', $shop->serie);
        $this->assertSame('392.70', (string) $shop->total);
        $this->assertSame('392.70', (string) $shop->sub_total);
        $this->assertSame('392.70', (string) $shop->base0);

        // El contacto creado debe ser el PROVEEDOR (identificacionProveedor), no la empresa.
        $contact = Contact::find($shop->contact_id);
        $this->assertSame('1306514736', $contact->identification);
        $this->assertSame('MENDOZA CEDEÑO JHONY REINALDO', $contact->name);
        $this->assertSame('01', $contact->provider_type);
        // Cédula (10 dígitos) => identification_type_id de CEDULA (id 2), no RUC.
        $this->assertSame(2, $contact->identification_type_id);

        $this->assertSame(1, $shop->items()->count());
        $item = $shop->items()->sole();
        $this->assertSame('2.3800', (string) $item->quantity);
        $this->assertSame('165.000000', (string) $item->unit_price);
    }

    public function test_skips_reimport_of_already_imported_document(): void
    {
        $xml = file_get_contents(__DIR__.'/../Fixtures/sri/liquidacion_compra.xml');
        $service = new ShopImportService(new SriSoapService, new SriXmlParserService);

        $service->importFromXml($xml, self::COMPANY_ID, self::COMPANY_RUC);
        $result = $service->importFromXml($xml, self::COMPANY_ID, self::COMPANY_RUC);

        $this->assertSame(['imported' => 0, 'skipped' => 1], $result);
    }
}
