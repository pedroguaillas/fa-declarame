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
        $voucherType = null;

        foreach (array_slice($lines, 1) as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < 8) {
                continue;
            }

            [$tipoComprobante, $serie, $claveAcceso, $fechaAutorizacion,
                $fechaEmision, $valorSinImpuestos, $iva, $total] = $cols;

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

            if ($sriData !== null) {
                $contact = Contact::firstOrCreate(
                    ['identification' => $sriData['identificacion_comprador']],
                    ['name' => $sriData['razon_social_comprador']],
                );

                if ($voucherType === null) {
                    $voucherType = VoucherType::where('code', substr($claveAcceso, 8, 2))->first();
                }

                Order::create([
                    'company_id' => $companyId,
                    'contact_id' => $contact->id,
                    'voucher_type_id' => $voucherType->id,
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
                [$base0, $base5, $base8, $base12, $base15, $iva5, $iva8, $iva12, $iva15] = $this->distributeIva($subTotal, $ivaAmount);

                Order::create([
                    'company_id' => $companyId,
                    'contact_id' => $consumidorFinal->id,
                    'voucher_type_id' => $voucherType->id,
                    'emision' => Carbon::createFromFormat('d/m/Y H:i:s', trim($fechaEmision))->format('Y-m-d'),
                    'autorization' => $claveAcceso,
                    'autorized_at' => Carbon::createFromFormat('d/m/Y H:i:s', trim($fechaAutorizacion))->format('Y-m-d H:i:s'),
                    'serie' => trim($serie),
                    'sub_total' => $subTotal,
                    'base0' => $base0,
                    'base5' => $base5,
                    'base8' => $base8,
                    'base12' => $base12,
                    'base15' => $base15,
                    'iva5' => $iva5,
                    'iva8' => $iva8,
                    'iva12' => $iva12,
                    'iva15' => $iva15,
                    'total' => $totalAmount,
                    'state' => 'AUTORIZADO',
                ]);
            }

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Distribute sub_total and IVA amount into the appropriate tax-rate buckets.
     * Used as fallback when the SRI SOAP service is unavailable.
     *
     * @return array{float, float, float, float, float, float, float, float, float}
     */
    private function distributeIva(float $subTotal, float $ivaAmount): array
    {
        if ($ivaAmount <= 0 || $subTotal <= 0) {
            return [$subTotal, 0, 0, 0, 0, 0, 0, 0, 0];
        }

        $ratio = $ivaAmount / $subTotal;
        $rates = [5 => 0.05, 8 => 0.08, 12 => 0.12, 15 => 0.15];
        $closest = 15;
        $minDiff = PHP_FLOAT_MAX;

        foreach ($rates as $rate => $value) {
            $diff = abs($ratio - $value);

            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $rate;
            }
        }

        return match ($closest) {
            5 => [0, $subTotal, 0, 0, 0, $ivaAmount, 0, 0, 0],
            8 => [0, 0, $subTotal, 0, 0, 0, $ivaAmount, 0, 0],
            12 => [0, 0, 0, $subTotal, 0, 0, 0, $ivaAmount, 0],
            default => [0, 0, 0, 0, $subTotal, 0, 0, 0, $ivaAmount],
        };
    }
}
