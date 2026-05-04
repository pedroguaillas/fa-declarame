<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\Retention;
use App\Models\Tenant\Shop;
use Carbon\Carbon;
use SimpleXMLElement;

class ShopRetentionImportService
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

            $claveAcceso = trim($cols[2]);

            if (strlen($claveAcceso) !== 49) {
                $skipped++;

                continue;
            }

            if (substr($claveAcceso, 8, 2) !== '07') {
                $skipped++;

                continue;
            }

            if (substr($claveAcceso, 10, 13) !== $companyRuc) {
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
            return $this->processV1($xml, $autorizacion, $serieRetention, $dateRetention, $autorizacionRetention);
        }

        return $this->processV2($xml, $autorizacion, $serieRetention, $dateRetention, $autorizacionRetention);
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
        string $serieRetention,
        string $dateRetention,
        string $autorizacionRetention,
    ): array {
        $docGroups = [];
        foreach ($xml->impuestos->impuesto as $impuesto) {
            $numDoc = trim((string) $impuesto->numDocSustento);
            if (! isset($docGroups[$numDoc])) {
                $docGroups[$numDoc] = [
                    'impuestos' => [],
                ];
            }
            $docGroups[$numDoc]['impuestos'][] = $impuesto;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($docGroups as $numDoc => $group) {
            $serie = substr($numDoc, 0, 3).'-'.substr($numDoc, 3, 3).'-'.substr($numDoc, 6);
            $shop = Shop::where('serie', $serie)->first();

            if (! $shop) {
                $skipped++;

                continue;
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

            $shop->update([
                'serie_retention' => $serieRetention,
                'date_retention' => $dateRetention,
                'autorization_retention' => $autorizacionRetention,
                'state_retention' => 'AUTORIZADO',
                'retention_at' => Carbon::parse((string) $autorizacion->fechaAutorizacion)->format('Y-m-d H:i:s'),
            ]);

            $shop->retentionItems()->delete();
            $shop->retentionItems()->createMany($items);

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
        string $serieRetention,
        string $dateRetention,
        string $autorizacionRetention,
    ): array {
        $imported = 0;
        $skipped = 0;

        foreach ($xml->docsSustento->docSustento as $docSustento) {
            $numAutDocSustento = trim((string) $docSustento->numAutDocSustento);
            $identificacionSujetoRetenido = trim((string) $docSustento->identificacionSujetoRetenido);

            $num = (string) $docSustento->numDocSustento;
            $formated = substr($num, 0, 3).'-'.substr($num, 3, 3).'-'.substr($num, 6);

            $shop = Shop::where('autorization', $numAutDocSustento)
                ->orWhere([
                    'serie' => $formated,
                    'contact_id' => Contact::where('identification', $identificacionSujetoRetenido)->value('id'),
                ])->first();

            if (! $shop) {
                $skipped++;

                continue;
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

            $shop->update([
                'serie_retention' => $serieRetention,
                'date_retention' => $dateRetention,
                'autorization_retention' => $autorizacionRetention,
                'state_retention' => 'AUTORIZADO',
                'retention_at' => Carbon::parse((string) $autorizacion->fechaAutorizacion)->format('Y-m-d H:i:s'),
            ]);

            $shop->retentionItems()->delete();
            $shop->retentionItems()->createMany($items);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
