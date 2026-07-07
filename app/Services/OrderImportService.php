<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\Order;
use App\Models\Tenant\Scopes\CompanyScope;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;
use SimpleXMLElement;

class OrderImportService
{
    /** @var array<string, string> */
    private array $voucherTypeMap = [
        'factura' => '01',
        'nota de crédito' => '04',
        'nota de débito' => '05',
    ];

    /** @var array<string, string> Comprobante root element => info node holding fechaEmision */
    private const COMPROBANTE_INFO_NODES = [
        'factura' => 'infoFactura',
        'notaCredito' => 'infoNotaCredito',
        'notaDebito' => 'infoNotaDebito',
    ];

    public function __construct(
        private readonly SriSoapService $sriSoapService,
        private readonly SriXmlParserService $xmlParser,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function import(string $content, int $companyId, string $companyRuc): array
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = preg_split('/\r?\n/', $content);
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $voucherTypeId = null;

        foreach (array_slice($lines, 1) as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < 8) {
                continue;
            }

            [$tipoComprobante, $serie, $claveAcceso, $fechaAutorizacion, $fechaEmision, $valorSinImpuestos, $iva, $total
            ] = $cols;

            $claveAcceso = trim($claveAcceso);

            $validCodes = array_values($this->voucherTypeMap);

            if (
                // Si la clave de acceso no tiene 49 digitos debe pasar
                strlen($claveAcceso) !== 49
                // Si la clave de acceso no tiene el RUC del contribuyente debe pasar
                || substr($claveAcceso, 10, 13) !== $companyRuc
                // Si el Comprobante no corresponde a los Tipos de Comprobantes permitidos debe pasar
                || ! in_array(substr($claveAcceso, 8, 2), $validCodes)
                // Si ya esta registrado debe pasar
                || Order::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('autorization', $claveAcceso)->exists()
            ) {
                $skipped++;

                continue;
            }

            $autorizacion = $this->sriSoapService->authorize($claveAcceso);

            $sriData = null;

            if ($autorizacion !== null) {
                $sriData = $this->xmlParser->parse($autorizacion);
            }

            $voucherTypeId ??= VoucherType::where('code', substr($claveAcceso, 8, 2))->value('id');

            if ($sriData !== null) {
                $contact = Contact::firstOrCreate(
                    ['identification' => $sriData['identificacion_comprador']],
                    [
                        'identification_type_id' => $sriData['tipoIdentificacionComprador'],
                        'name' => $sriData['razon_social_comprador'],
                        'provider_type' => strlen($sriData['identificacion_comprador']) === 13 && in_array($sriData['identificacion_comprador'][2], ['6', '9']) ? '02' : '01',
                    ],
                );

                Order::create([
                    'company_id' => $companyId,
                    'contact_id' => $contact->id,
                    'voucher_type_id' => $voucherTypeId,
                    'emision' => $sriData['fecha_emision'],
                    'autorization' => $claveAcceso,
                    'autorized_at' => $sriData['fecha_autorizacion'],
                    'serie' => $sriData['serie'],
                    'sub_total' => $sriData['sub_total'],
                    'base0' => $sriData['base0'],
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
                ]);
            } else {
                $consumidorFinal = Contact::firstOrCreate(
                    ['identification' => '9999999999999'],
                    ['name' => 'Consumidor Final'],
                );

                $subTotal = (float) $valorSinImpuestos;
                $ivaAmount = (float) $iva;
                $totalAmount = (float) $total;

                Order::create([
                    'company_id' => $companyId,
                    'contact_id' => $consumidorFinal->id,
                    'voucher_type_id' => $voucherTypeId,
                    'emision' => Carbon::createFromFormat('d/m/Y H:i:s', trim($fechaEmision))->format('Y-m-d'),
                    'autorization' => $claveAcceso,
                    'autorized_at' => Carbon::createFromFormat('d/m/Y H:i:s', trim($fechaAutorizacion))->format('Y-m-d H:i:s'),
                    'serie' => trim($serie),
                    'sub_total' => $subTotal,
                    'total' => $totalAmount,
                    'state' => 'PENDIENTE',
                ]);
            }

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import XML and TXT files from a ZIP archive.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function importFromZip(string $zipPath, int $companyId, string $companyRuc): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($zipPath) !== true) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 1];
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (! in_array($extension, ['xml', 'txt'])) {
                continue;
            }

            $content = $zip->getFromIndex($i);

            if ($content === false) {
                $skipped++;

                continue;
            }

            $result = $extension === 'xml'
                ? $this->importFromXml($content, $companyId, $companyRuc)
                : $this->import($content, $companyId, $companyRuc);

            $imported += $result['imported'];
            $skipped += $result['skipped'];
            $errors += $result['errors'] ?? 0;
        }

        $zip->close();

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
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
        $claveAcceso = (string) $autorizacion->numeroAutorizacion;
        $validCodes = array_values($this->voucherTypeMap);

        if (
            strlen($claveAcceso) !== 49
            || substr($claveAcceso, 10, 13) !== $companyRuc
            || ! in_array(substr($claveAcceso, 8, 2), $validCodes)
            || Order::withoutGlobalScope(CompanyScope::class)->where('company_id', $companyId)->where('autorization', $claveAcceso)->exists()
        ) {
            return ['imported' => 0, 'skipped' => 1];
        }

        $sriData = $this->xmlParser->parse($autorizacion);

        if ($sriData === null) {
            return ['imported' => 0, 'skipped' => 1];
        }

        $contact = Contact::firstOrCreate(
            ['identification' => $sriData['identificacion_comprador']],
            [
                'identification_type_id' => $sriData['tipoIdentificacionComprador'],
                'name' => $sriData['razon_social_comprador'],
                'provider_type' => strlen($sriData['identificacion_comprador']) === 13 && in_array($sriData['identificacion_comprador'][2], ['6', '9']) ? '02' : '01',
            ],
        );

        $voucherTypeId = VoucherType::where('code', substr($claveAcceso, 8, 2))->value('id');

        Order::create([
            'company_id' => $companyId,
            'contact_id' => $contact->id,
            'voucher_type_id' => $voucherTypeId,
            'emision' => $sriData['fecha_emision'],
            'autorization' => $claveAcceso,
            'autorized_at' => $sriData['fecha_autorizacion'],
            'serie' => $sriData['serie'],
            'sub_total' => $sriData['sub_total'],
            'base0' => $sriData['base0'],
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
        ]);

        return ['imported' => 1, 'skipped' => 0];
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

        $autorizacion = $xml->getName() === 'autorizacion' ? $xml : ($xml->autorizacion ?? null);

        if ($autorizacion !== null && isset($autorizacion->comprobante)) {
            return (object) [
                'estado' => (string) ($autorizacion->estado ?? 'AUTORIZADO'),
                'numeroAutorizacion' => (string) ($autorizacion->numeroAutorizacion ?? ''),
                'fechaAutorizacion' => (string) ($autorizacion->fechaAutorizacion ?? ''),
                'comprobante' => (string) $autorizacion->comprobante,
            ];
        }

        return $this->wrapComprobanteXml($xml, $xmlContent);
    }

    /**
     * Build a synthetic authorization wrapper for a bare comprobante XML
     * (root <factura>, <notaCredito> or <notaDebito> without <autorizacion>).
     * The clave de acceso doubles as numeroAutorizacion and fechaEmision as fechaAutorizacion.
     */
    private function wrapComprobanteXml(SimpleXMLElement $xml, string $xmlContent): ?object
    {
        $infoNode = self::COMPROBANTE_INFO_NODES[$xml->getName()] ?? null;
        $claveAcceso = trim((string) ($xml->infoTributaria->claveAcceso ?? ''));

        if ($infoNode === null || $claveAcceso === '' || ! isset($xml->{$infoNode})) {
            return null;
        }

        try {
            $fechaEmision = Carbon::createFromFormat('d/m/Y', trim((string) $xml->{$infoNode}->fechaEmision))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }

        return (object) [
            'estado' => 'AUTORIZADO',
            'numeroAutorizacion' => $claveAcceso,
            'fechaAutorizacion' => $fechaEmision,
            'comprobante' => $xmlContent,
        ];
    }
}
