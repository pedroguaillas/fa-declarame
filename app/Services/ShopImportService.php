<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Product;
use App\Models\Tenant\Shop;
use App\Models\Tenant\TaxSupport;
use App\Models\Tenant\VoucherType;
use Constants;

class ShopImportService
{
    /** @var array<string, string> */
    private array $voucherTypeMap = [
        'factura' => '01',
        'liquidación de compra de bienes y prestación de servicios' => '03',
        'nota de crédito' => '04',
        'nota de débito' => '05',
    ];

    private ?int $identificationTypeId = null;

    private ?int $taxSupportId = null;

    /** @var array<string, int> */
    private array $voucherTypeIdCache = [];

    public function __construct(
        private readonly SriSoapService $sriSoapService,
        private readonly SriXmlParserService $xmlParser,
    ) {}

    private function getIdentificationTypeId(): int
    {
        return $this->identificationTypeId ??= IdentificationType::where('code_shop', Constants::RUC_COMPRA)->value('id');
    }

    private function getTaxSupportId(): int
    {
        return $this->taxSupportId ??= TaxSupport::where('code', '01')->value('id');
    }

    private function getVoucherTypeId(string $code): int
    {
        return $this->voucherTypeIdCache[$code] ??= VoucherType::where('code', $code)->value('id');
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function import(string $content, int $companyId, string $companyRuc): array
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = preg_split('/\r?\n/', $content);
        $imported = 0;
        $skipped = 0;

        $validCodes = array_values($this->voucherTypeMap);

        foreach (array_slice($lines, 1) as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < 10) {
                continue;
            }

            // TODO: no siempre va ser este orden buscar clave de acceso, tanto en ventas y retenciones
            [, , , , $claveAcceso] = $cols;

            $claveAcceso = trim($claveAcceso);

            if (
                strlen($claveAcceso) !== 49
                || ! in_array(substr($claveAcceso, 8, 2), $validCodes)
                || Shop::where('autorization', $claveAcceso)->exists()
            ) {
                $skipped++;

                continue;
            }

            $autorizacion = $this->sriSoapService->authorize($claveAcceso);

            if ($autorizacion === null) {
                $skipped++;

                continue;
            }

            $sriData = $this->xmlParser->parse($autorizacion);

            if ($sriData === null) {
                $skipped++;

                continue;
            }

            if ($this->createShopFromSriData($sriData, $claveAcceso, $companyId, $companyRuc)) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Import a single XML authorization file (SRI format).
     *
     * @return array{imported: int, skipped: int}
     */
    public function importFromXml(string $xmlContent, int $companyId, string $companyRuc): array
    {
        $autorizacion = $this->parseAutorizacionXml($xmlContent);

        if ($autorizacion === null) {
            return ['imported' => 0, 'skipped' => 1];
        }

        $sriData = $this->xmlParser->parse($autorizacion);

        if ($sriData === null) {
            return ['imported' => 0, 'skipped' => 1];
        }

        if ($this->createShopFromSriData($sriData, (string) $autorizacion->numeroAutorizacion, $companyId, $companyRuc)) {
            return ['imported' => 1, 'skipped' => 0];
        }

        return ['imported' => 0, 'skipped' => 1];
    }

    /**
     * Import multiple XML authorization files from a ZIP.
     *
     * @return array{imported: int, skipped: int}
     */
    public function importFromZip(string $zipPath, int $companyId, string $companyRuc): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $imported = 0;
        $skipped = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }

            $content = $zip->getFromIndex($i);

            if ($content === false) {
                $skipped++;

                continue;
            }

            $result = $this->importFromXml($content, $companyId, $companyRuc);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
        }

        $zip->close();

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Parse an SRI authorization XML string into a stdClass matching the SOAP response structure.
     */
    private function parseAutorizacionXml(string $xmlContent): ?object
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();

        if ($xml === false) {
            return null;
        }

        // Handle both <autorizacion> root and wrapped structures
        $autorizacion = $xml->getName() === 'autorizacion' ? $xml : ($xml->autorizacion ?? null);

        if ($autorizacion === null || ! isset($autorizacion->comprobante)) {
            return null;
        }

        return (object) [
            'estado' => (string) ($autorizacion->estado ?? 'AUTORIZADO'),
            'numeroAutorizacion' => (string) ($autorizacion->numeroAutorizacion ?? ''),
            'fechaAutorizacion' => (string) ($autorizacion->fechaAutorizacion ?? ''),
            'comprobante' => (string) $autorizacion->comprobante,
        ];
    }

    /**
     * Create a Shop record from parsed SRI data. Returns true if created, false if skipped.
     */
    private function createShopFromSriData(array $sriData, string $claveAcceso, int $companyId, string $companyRuc): bool
    {
        $validCodes = array_values($this->voucherTypeMap);

        if (! in_array($sriData['cod_doc'], $validCodes)) {
            return false;
        }

        if (Shop::where('autorization', $claveAcceso)->exists()) {
            return false;
        }

        $buyerId = $sriData['identificacion_comprador'];
        $cedula = substr($companyRuc, 0, 10);
        $withCedula = $buyerId === $cedula && $buyerId !== $companyRuc;

        if ($buyerId !== $companyRuc && $buyerId !== $cedula) {
            return false;
        }

        $rucEmisor = $sriData['ruc_emisor'];

        $contact = Contact::firstOrCreate(
            ['identification' => trim($rucEmisor)],
            [
                'identification_type_id' => $this->getIdentificationTypeId(),
                'name' => $sriData['razon_social_emisor'],
                'provider_type' => strlen($rucEmisor) === 13 && in_array($rucEmisor[2], ['6', '9']) ? '02' : '01',
                'contributor_type_id' => $sriData['contributor_type_id'],
            ],
        );

        $voucherTypeId = $this->getVoucherTypeId($sriData['cod_doc']);

        $shop = Shop::create([
            'company_id' => $companyId,
            'contact_id' => $contact->id,
            'voucher_type_id' => $voucherTypeId,
            'tax_support_id' => $this->getTaxSupportId(),
            'emision' => $sriData['fecha_emision'],
            'autorization' => $claveAcceso,
            'autorized_at' => $sriData['fecha_autorizacion'],
            'serie' => $sriData['serie'],
            'sub_total' => $sriData['sub_total'],
            'base0' => $sriData['base0'],
            'exempt' => $sriData['exempt'],
            'no_iva' => $sriData['no_iva'],
            'base5' => $sriData['base5'],
            'base8' => $sriData['base8'],
            'base12' => $sriData['base12'],
            'base15' => $sriData['base15'],
            'iva5' => $sriData['iva5'],
            'iva8' => $sriData['iva8'],
            'iva12' => $sriData['iva12'],
            'iva15' => $sriData['iva15'],
            'discount' => $sriData['discount'],
            'total' => $sriData['total'],
            'state' => $sriData['estado'],
            'est_modify' => $sriData['est_modify'],
            'poi_modify' => $sriData['poi_modify'],
            'sec_modify' => $sriData['sec_modify'],
            'data_additional' => ['with_cedula' => $withCedula],
        ]);

        $this->createShopItems($shop, $sriData['detalles'] ?? [], $contact->id);

        return true;
    }

    /**
     * @param  array<int, array{code: string, aux_code: string|null, description: string, quantity: float, unit_price: float, discount: float, total: float, tax_percentage: float, tax_value: float}>  $detalles
     */
    private function createShopItems(Shop $shop, array $detalles, int $contactId): void
    {
        if (empty($detalles)) {
            return;
        }

        $items = [];

        foreach ($detalles as $detalle) {
            $product = Product::firstOrCreate(
                [
                    'code' => $detalle['code'],
                    'description' => $detalle['description'],
                    'contact_id' => $contactId,
                ],
                ['aux_code' => $detalle['aux_code']],
            );

            $items[] = [
                'product_id' => $product->id,
                'quantity' => $detalle['quantity'],
                'unit_price' => $detalle['unit_price'],
                'discount' => $detalle['discount'],
                'total' => $detalle['total'],
                'tax_percentage' => $detalle['tax_percentage'],
                'tax_value' => $detalle['tax_value'],
            ];
        }

        $shop->items()->createMany($items);
    }
}
