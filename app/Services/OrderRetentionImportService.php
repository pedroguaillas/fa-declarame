<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Order;
use App\Models\Tenant\Retention;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;
use Constants;
use SimpleXMLElement;

class OrderRetentionImportService
{
    public function __construct(
        private readonly SriSoapService $sriSoapService,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: int, failedKeys: array<string>}
     */
    public function import(string $content, string $companyRuc): array
    {
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }

        $lines = preg_split('/\r?\n/', $content);
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $failedKeys = [];

        foreach (array_slice($lines, 1) as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < 3) {
                continue;
            }

            $claveAcceso = trim($cols[4]);

            if (strlen($claveAcceso) !== 49) {
                $skipped++;
                $failedKeys[] = $claveAcceso;

                continue;
            }

            if (substr($claveAcceso, 8, 2) !== '07') {
                $skipped++;
                $failedKeys[] = $claveAcceso;

                continue;
            }

            $autorizacion = $this->sriSoapService->authorize($claveAcceso);

            if ($autorizacion === null) {
                $errors++;
                $failedKeys[] = $claveAcceso;

                continue;
            }

            $result = $this->processRetention($autorizacion, $companyRuc);
            $imported += $result['imported'];
            $skipped += $result['skipped'];

            if ($result['imported'] === 0) {
                $failedKeys[] = $claveAcceso;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'failedKeys' => $failedKeys];
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    private function processRetention(object $autorizacion, string $companyRuc): array
    {
        $comprobanteXml = (string) $autorizacion->comprobante;
        $xml = new SimpleXMLElement($comprobanteXml, LIBXML_NONET);

        $identificacionSujeto = (string) $xml->infoCompRetencion->identificacionSujetoRetenido;
        $cedula = substr($companyRuc, 0, 10);

        // Only process retentions issued to this company
        if ($identificacionSujeto !== $companyRuc && $identificacionSujeto !== $cedula) {
            return ['imported' => 0, 'skipped' => 1];
        }

        $estab = (string) $xml->infoTributaria->estab;
        $ptoEmi = (string) $xml->infoTributaria->ptoEmi;
        $secuencial = (string) $xml->infoTributaria->secuencial;
        $serieRetention = "{$estab}-{$ptoEmi}-{$secuencial}";

        $fechaEmision = (string) $xml->infoCompRetencion->fechaEmision;
        $dateRetention = Carbon::createFromFormat('d/m/Y', $fechaEmision)->format('Y-m-d');

        $autorizacionRetention = (string) $autorizacion->numeroAutorizacion;
        if (empty($autorizacionRetention)) {
            $autorizacionRetention = (string) $xml->infoTributaria->claveAcceso;
        }

        $version = (string) ($xml->attributes()['version'] ?? '2.0.0');

        if ($version === '1.0.0') {
            return $this->processV1($xml, $autorizacion, $companyRuc, $serieRetention, $dateRetention, $autorizacionRetention);
        }

        return $this->processV2($xml, $autorizacion, $companyRuc, $serieRetention, $dateRetention, $autorizacionRetention);
    }

    /**
     * Process v1.0.0: impuestos/impuesto — each item carries its own document reference.
     * Groups items by numDocSustento so all retentions for the same document are applied together.
     *
     * @return array{imported: int, skipped: int}
     */
    private function processV1(
        SimpleXMLElement $xml,
        object $autorizacion,
        string $companyRuc,
        string $serieRetention,
        string $dateRetention,
        string $autorizacionRetention,
    ): array {
        // Group impuesto elements by numDocSustento
        $docGroups = [];
        foreach ($xml->impuestos->impuesto as $impuesto) {
            $numDoc = trim((string) $impuesto->numDocSustento);
            if (! isset($docGroups[$numDoc])) {
                $docGroups[$numDoc] = [
                    'codDocSustento' => (string) $impuesto->codDocSustento,
                    'fechaEmisionDocSustento' => (string) $impuesto->fechaEmisionDocSustento,
                    'impuestos' => [],
                ];
            }
            $docGroups[$numDoc]['impuestos'][] = $impuesto;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($docGroups as $numDoc => $group) {
            $serie = substr($numDoc, 0, 3).'-'.substr($numDoc, 3, 3).'-'.substr($numDoc, 6);
            $emisionDoc = Carbon::createFromFormat('d/m/Y', $group['fechaEmisionDocSustento'])->format('Y-m-d');

            // V1 no trae autorización del documento sustento → buscar por serie + fecha
            // para evitar colisiones con comprobantes físicos de otra época.
            $order = Order::where('serie', $serie)
                ->whereDate('emision', $emisionDoc)
                ->first();

            if (! $order) {
                $voucherType = VoucherType::where([
                    'code' => $group['codDocSustento'],
                    'initial' => 'OTRO',
                ])->first();

                if ($voucherType) {
                    $order = $this->storeOrderV1($xml, $group, $serie, $companyRuc, $voucherType);
                } else {
                    $skipped++;

                    continue;
                }
            }

            $items = [];
            foreach ($group['impuestos'] as $impuesto) {
                $codigoRetencion = trim((string) $impuesto->codigoRetencion);
                $base = (float) $impuesto->baseImponible;
                $percentage = (float) $impuesto->porcentajeRetener;
                $value = (float) $impuesto->valorRetenido;
                $retention = Retention::where('code', $codigoRetencion)->first();

                if (! $retention) {
                    continue;
                }

                $items[] = [
                    'retention_id' => $retention->id,
                    'base' => $base,
                    'percentage' => $percentage,
                    'value' => $value,
                ];
            }

            if (empty($items)) {
                $skipped++;

                continue;
            }

            $order->update([
                'serie_retention' => $serieRetention,
                'date_retention' => $dateRetention,
                'autorization_retention' => $autorizacionRetention,
                'state_retention' => 'AUTORIZADO',
                'retention_at' => Carbon::parse((string) $autorizacion->fechaAutorizacion)->format('Y-m-d H:i:s'),
            ]);

            $order->retentionItems()->delete();
            $order->retentionItems()->createMany($items);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Process v2.0.0: docsSustento/docSustento — each docSustento groups its retentions.
     *
     * @return array{imported: int, skipped: int}
     */
    private function processV2(
        SimpleXMLElement $xml,
        object $autorizacion,
        string $companyRuc,
        string $serieRetention,
        string $dateRetention,
        string $autorizacionRetention,
    ): array {
        $imported = 0;
        $skipped = 0;

        foreach ($xml->docsSustento->docSustento as $docSustento) {
            $numAutDocSustento = trim((string) $docSustento->numAutDocSustento);

            $num = (string) $docSustento->numDocSustento;
            $serie = substr($num, 0, 3).'-'.substr($num, 3, 3).'-'.substr($num, 6);

            $emisionDoc = Carbon::createFromFormat('d/m/Y', (string) $docSustento->fechaEmisionDocSustento)->format('Y-m-d');

            // Clave de acceso electrónica (49 dígitos): globalmente única → buscar solo por autorización.
            // Autorización física (< 49 dígitos): puede repetirse entre períodos → buscar por serie + fecha.
            if (strlen($numAutDocSustento) === 49) {
                $order = Order::where('autorization', $numAutDocSustento)->first();
            } else {
                $order = Order::where('serie', $serie)
                    ->whereDate('emision', $emisionDoc)
                    ->first();
            }

            if (! $order) {
                $voucherType = VoucherType::where([
                    'code' => (string) $docSustento->codDocSustento,
                    'initial' => 'OTRO',
                ])->first();

                if ($voucherType) {
                    $order = $this->storeOrderV2($xml, $docSustento, $companyRuc, $voucherType);
                } else {
                    $skipped++;

                    continue;
                }
            }

            $items = [];
            foreach ($docSustento->retenciones->retencion as $retencion) {
                $codigoRetencion = trim((string) $retencion->codigoRetencion);
                $base = (float) $retencion->baseImponible;
                $percentage = (float) $retencion->porcentajeRetener;
                $value = (float) $retencion->valorRetenido;
                $retention = Retention::where('code', $codigoRetencion)->first();

                if (! $retention) {
                    continue;
                }

                $items[] = [
                    'retention_id' => $retention->id,
                    'base' => $base,
                    'percentage' => $percentage,
                    'value' => $value,
                ];
            }

            if (empty($items)) {
                $skipped++;

                continue;
            }

            $order->update([
                'serie_retention' => $serieRetention,
                'date_retention' => $dateRetention,
                'autorization_retention' => $autorizacionRetention,
                'state_retention' => 'AUTORIZADO',
                'retention_at' => Carbon::parse((string) $autorizacion->fechaAutorizacion)->format('Y-m-d H:i:s'),
            ]);

            $order->retentionItems()->delete();
            $order->retentionItems()->createMany($items);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Create an Order from v1.0.0 data.
     * v1.0.0 does not include authorization number or IVA breakdown for the source document,
     * so sub_total is approximated from the sum of retention bases.
     *
     * @param  array{codDocSustento: string, fechaEmisionDocSustento: string, impuestos: array<SimpleXMLElement>}  $group
     */
    private function storeOrderV1(
        SimpleXMLElement $retentionXml,
        array $group,
        string $serie,
        string $companyRuc,
        VoucherType $voucherType,
    ): Order {
        $company = Company::where('ruc', $companyRuc)->firstOrFail();

        $retainerRuc = (string) $retentionXml->infoTributaria->ruc;
        $retainerName = (string) $retentionXml->infoTributaria->razonSocial;

        $contact = Contact::firstOrCreate(
            ['identification' => $retainerRuc],
            ['name' => $retainerName],
        );

        $emision = Carbon::createFromFormat('d/m/Y', $group['fechaEmisionDocSustento'])->format('Y-m-d');

        $subTotal = (float) array_sum(
            array_map(fn (SimpleXMLElement $i) => (float) $i->baseImponible, $group['impuestos'])
        );

        return Order::create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'voucher_type_id' => $voucherType->id,
            'emision' => $emision,
            'autorization' => '',
            'serie' => $serie,
            'sub_total' => $subTotal,
            'total' => $subTotal,
            'state' => 'AUTORIZADO',
        ]);
    }

    /**
     * Create an Order from v2.0.0 data.
     */
    private function storeOrderV2(
        SimpleXMLElement $retentionXml,
        SimpleXMLElement $docSustento,
        string $companyRuc,
        VoucherType $voucherType,
    ): Order {
        $company = Company::where('ruc', $companyRuc)->firstOrFail();

        $retainerRuc = (string) $retentionXml->infoTributaria->ruc;
        $retainerName = (string) $retentionXml->infoTributaria->razonSocial;
        $identificationTypeId = IdentificationType::where('code_shop', Constants::RUC_COMPRA)->value('id');

        $contact = Contact::firstOrCreate(
            [
                'identification' => $retainerRuc,
                'identification_type_id' => $identificationTypeId,
            ],
            ['name' => $retainerName],
        );

        $num = (string) $docSustento->numDocSustento;
        $serie = substr($num, 0, 3).'-'.substr($num, 3, 3).'-'.substr($num, 6);

        $emision = Carbon::createFromFormat('d/m/Y', (string) $docSustento->fechaEmisionDocSustento)->format('Y-m-d');
        $autorization = trim((string) $docSustento->numAutDocSustento);

        return Order::create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'voucher_type_id' => $voucherType->id,
            'emision' => $emision,
            'autorization' => $autorization,
            'serie' => $serie,
            'state' => 'AUTORIZADO',
        ]);
    }
}
