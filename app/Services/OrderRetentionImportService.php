<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Contact;
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
     * @return array{imported: int, skipped: int, errors: int}
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

                continue;
            }

            if (substr($claveAcceso, 8, 2) !== '07') {
                $skipped++;

                continue;
            }

            $autorizacion = $this->sriSoapService->authorize($claveAcceso);

            if ($autorizacion === null) {
                $errors++;

                continue;
            }

            $result = $this->processRetention($autorizacion, $companyRuc);
            $imported += $result['imported'];
            $skipped += $result['skipped'];
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    private function processRetention(object $autorizacion, string $companyRuc): array
    {
        $comprobanteXml = (string) $autorizacion->comprobante;
        $xml = new SimpleXMLElement($comprobanteXml);

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

            // TODO: Condicionar
            // Crear el Scope para companyId
            // Que sea de esa fecha o mes
            // Si tiene el autorizacion esmas efectivo
            $order = Order::where('serie', $serie)->first();

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

            $order = Order::where('autorization', $numAutDocSustento)
                ->orWhere('serie', $serie)->first();

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

        $contact = Contact::firstOrCreate(
            ['identification' => $retainerRuc],
            ['name' => $retainerName],
        );

        $num = (string) $docSustento->numDocSustento;
        $serie = substr($num, 0, 3).'-'.substr($num, 3, 3).'-'.substr($num, 6);

        $emision = Carbon::createFromFormat('d/m/Y', (string) $docSustento->fechaEmisionDocSustento)->format('Y-m-d');
        $autorization = trim((string) $docSustento->numAutDocSustento);

        [$base0, $noIva, $base5, $base8, $base12, $base15, $iva5, $iva8, $iva12, $iva15] = $this->extractIva($docSustento);

        $subTotal = $base0 + $noIva + $base5 + $base8 + $base12 + $base15;
        $total = $subTotal + $iva5 + $iva8 + $iva12 + $iva15;

        return Order::create([
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'voucher_type_id' => $voucherType->id,
            'emision' => $emision,
            'autorization' => $autorization,
            'serie' => $serie,
            'sub_total' => $subTotal,
            'base0' => $base0,
            'no_iva' => $noIva,
            'base5' => $base5,
            'base8' => $base8,
            'base12' => $base12,
            'base15' => $base15,
            'iva5' => $iva5,
            'iva8' => $iva8,
            'iva12' => $iva12,
            'iva15' => $iva15,
            'total' => $total,
            'state' => 'AUTORIZADO',
        ]);
    }

    /**
     * Parse detalleImpuestos from a v2.0.0 docSustento node.
     *
     * @return array{float, float, float, float, float, float, float, float, float, float}
     *                                                                                     [base0, no_iva, base5, base8, base12, base15, iva5, iva8, iva12, iva15]
     */
    private function extractIva(SimpleXMLElement $docSustento): array
    {
        $base0 = 0.0;
        $noIva = 0.0;
        $base5 = 0.0;
        $base8 = 0.0;
        $base12 = 0.0;
        $base15 = 0.0;
        $iva5 = 0.0;
        $iva8 = 0.0;
        $iva12 = 0.0;
        $iva15 = 0.0;

        if (! isset($docSustento->detalleImpuestos->detalleImpuesto)) {
            return [$base0, $noIva, $base5, $base8, $base12, $base15, $iva5, $iva8, $iva12, $iva15];
        }

        foreach ($docSustento->detalleImpuestos->detalleImpuesto as $impuesto) {
            // codigo 2 = IVA; skip ICE (3) and others
            if ((int) $impuesto->codigo !== 2) {
                continue;
            }

            $code = (int) $impuesto->codigoPorcentaje;
            $base = (float) $impuesto->baseImponible;
            $valor = (float) $impuesto->impuesto;

            if ($code === Constants::IVA0 || $code === Constants::IVA_EXENT0) {
                $base0 += $base;
            } elseif ($code === Constants::NO_IVA) {
                $noIva += $base;
            } elseif ($code === Constants::IVA5) {
                $base5 += $base;
                $iva5 += $valor;
            } elseif ($code === Constants::IVA_DIFERIDO) {
                $base12 += $base;
                $iva12 += $valor;
            } elseif ($code === Constants::IVA12) {
                $base12 += $base;
                $iva12 += $valor;
            } elseif ($code === Constants::IVA15) {
                $base15 += $base;
                $iva15 += $valor;
            }
        }

        return [$base0, $noIva, $base5, $base8, $base12, $base15, $iva5, $iva8, $iva12, $iva15];
    }
}
