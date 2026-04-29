<?php

namespace App\Services;

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
    private function processRetention(object $autorizacion): array
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

        $imported = 0;
        $skipped = 0;

        foreach ($xml->docsSustento->docSustento as $docSustento) {
            $numAutDocSustento = trim((string) $docSustento->numAutDocSustento);

            $num = $docSustento->numDocSustento; // "002901000019695"
            $formated = substr($num, 0, 3).'-'.substr($num, 3, 3).'-'.substr($num, 6);

            $shop = Shop::where('autorization', $numAutDocSustento)
                ->orWhere('serie', $formated)->first();

            if (! $shop) {
                $skipped++;

                continue;
            }

            // Build retention items from XML
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
