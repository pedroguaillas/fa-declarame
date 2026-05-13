<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\Order;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;

class OrderImportService
{
    /** @var array<string, string> */
    private array $voucherTypeMap = [
        'factura' => '01',
        'nota de crédito' => '04',
        'nota de débito' => '05',
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
                || Order::where('autorization', $claveAcceso)->exists()
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

        $claveAcceso = (string) $autorizacion->numeroAutorizacion;
        $validCodes = array_values($this->voucherTypeMap);

        if (
            strlen($claveAcceso) !== 49
            || substr($claveAcceso, 10, 13) !== $companyRuc
            || ! in_array(substr($claveAcceso, 8, 2), $validCodes)
            || Order::where('autorization', $claveAcceso)->exists()
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
}
