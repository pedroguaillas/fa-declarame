<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Product;
use App\Models\Tenant\Scopes\CompanyScope;
use App\Models\Tenant\Shop;
use App\Models\Tenant\TaxSupport;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;
use Constants;
use SimpleXMLElement;

class ShopImportService
{
    /** @var array<string, string> */
    private array $voucherTypeMap = [
        'factura' => '01',
        'liquidación de compra de bienes y prestación de servicios' => '03',
        'nota de crédito' => '04',
        'nota de débito' => '05',
    ];

    private ?int $taxSupportId = null;

    /** @var array<string, int> */
    private array $voucherTypeIdCache = [];

    /** @var array<string, int> */
    private array $identificationTypeIdCache = [];

    public function __construct(
        private readonly SriSoapService $sriSoapService,
        private readonly SriXmlParserService $xmlParser,
    ) {}

    /**
     * RUC (13 dígitos) o Cédula (10 dígitos); cualquier otra longitud se considera Pasaporte.
     */
    private function getIdentificationTypeId(string $identification): int
    {
        $code = match (strlen($identification)) {
            13 => Constants::RUC_COMPRA,
            10 => Constants::CEDULA_COMPRA,
            default => Constants::PASAPORTE_COMPRA,
        };

        return $this->identificationTypeIdCache[$code]
            ??= IdentificationType::where('code_shop', $code)->value('id');
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
                || Shop::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('autorization', $claveAcceso)->exists()
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

        return $this->processFromAutorizacion($autorizacion, (string) $autorizacion->numeroAutorizacion, $companyId, $companyRuc);
    }

    /**
     * Process an already-fetched SOAP/XML authorization object (skip re-parsing).
     * Used by ProcessSoapClaveJob to avoid re-fetching from SOAP.
     *
     * @return array{imported: int, skipped: int}
     */
    public function processFromAutorizacion(object $autorizacion, string $claveAcceso, int $companyId, string $companyRuc): array
    {
        $sriData = $this->xmlParser->parse($autorizacion);

        if ($sriData === null) {
            return ['imported' => 0, 'skipped' => 1];
        }

        if ($this->createShopFromSriData($sriData, $claveAcceso, $companyId, $companyRuc)) {
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

    /** Raíces válidas de comprobante SRI que puede llegar "pelado" (sin el wrapper &lt;autorizacion&gt;). */
    private const COMPROBANTE_ROOTS = ['factura', 'liquidacionCompra', 'notaCredito', 'notaDebito'];

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

        $rootName = $xml->getName();

        // Handle both <autorizacion> root and wrapped structures
        $autorizacion = $rootName === 'autorizacion' ? $xml : ($xml->autorizacion ?? null);

        if ($autorizacion !== null && isset($autorizacion->comprobante)) {
            return (object) [
                'estado' => (string) ($autorizacion->estado ?? 'AUTORIZADO'),
                'numeroAutorizacion' => (string) ($autorizacion->numeroAutorizacion ?? ''),
                'fechaAutorizacion' => (string) ($autorizacion->fechaAutorizacion ?? ''),
                'comprobante' => (string) $autorizacion->comprobante,
            ];
        }

        // Comprobante autorizado firmado directamente (sin wrapper <autorizacion>), como el que
        // genera el propio contribuyente en Liquidación de Compra: la clave de acceso hace de
        // número de autorización y la fecha de firma XAdES sirve de fecha de autorización.
        if (! in_array($rootName, self::COMPROBANTE_ROOTS, true)) {
            return null;
        }

        $claveAcceso = trim((string) ($xml->infoTributaria->claveAcceso ?? ''));

        if ($claveAcceso === '') {
            return null;
        }

        return (object) [
            'estado' => 'AUTORIZADO',
            'numeroAutorizacion' => $claveAcceso,
            'fechaAutorizacion' => $this->extractSigningTime($xml) ?? $this->extractDateFromClaveAcceso($claveAcceso),
            'comprobante' => $xmlContent,
        ];
    }

    private function extractSigningTime(SimpleXMLElement $xml): ?string
    {
        $xml->registerXPathNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');
        $nodes = $xml->xpath('//xades:SigningTime');

        if (empty($nodes)) {
            return null;
        }

        try {
            return Carbon::parse((string) $nodes[0])->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Clave de acceso: los primeros 8 dígitos son la fecha de emisión (ddMMyyyy).
     */
    private function extractDateFromClaveAcceso(string $claveAcceso): string
    {
        try {
            return Carbon::createFromFormat('dmY', substr($claveAcceso, 0, 8))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return now()->format('Y-m-d H:i:s');
        }
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

        if (Shop::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('autorization', $claveAcceso)->exists()) {
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
                'identification_type_id' => $this->getIdentificationTypeId(trim($rucEmisor)),
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
