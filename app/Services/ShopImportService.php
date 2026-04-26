<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Shop;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;
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

            if (count($cols) < 10) {
                continue;
            }

            // TODO: no siempre va ser este orden buscar clave de acceso, tanto en ventas y retenciones
            [$rucEmisor, $razonSocial, $tipoComprobante, $serie, $claveAcceso,
                $fechaAutorizacion, $fechaEmision, , $valorSinImpuestos, $iva, $total] = $cols;

            $claveAcceso = trim($claveAcceso);

            $validCodes = array_values($this->voucherTypeMap);

            if (
                // Si la clave de acceso no tiene 49 digitos debe pasar
                strlen($claveAcceso) !== 49
                // Si el Comprobante no corresponde a los Tipos de Comprobantes permitidos debe pasar
                || ! in_array(substr($claveAcceso, 8, 2), $validCodes)
                // Si ya esta registrado debe pasar
                || Shop::where('autorization', $claveAcceso)->exists()
            ) {
                $skipped++;

                continue;
            }

            $autorizacion = $this->sriSoapService->authorize($claveAcceso);

            $sriData = null;

            if ($autorizacion !== null) {
                $sriData = $this->xmlParser->parse($autorizacion);
            }

            // Validate that this document was issued TO this company
            if ($sriData !== null) {
                $buyerId = $sriData['identificacion_comprador'];
                $cedula = substr($companyRuc, 0, 10);

                if ($buyerId !== $companyRuc && $buyerId !== $cedula) {
                    $skipped++;

                    continue;
                }
            }

            // Cachear
            $identification_type = IdentificationType::where('code_shop', Constants::RUC_COMPRA)->get()->first();

            $contact = Contact::firstOrCreate(
                ['identification' => trim($rucEmisor)],
                [
                    'name' => $sriData['razon_social_emisor'] ?? trim($razonSocial),
                    'identification_type_id' => $identification_type->id,
                    // TODO: añadir Tipo de Contribuyente
                ],
            );

            // Cachear
            if ($voucherType === null) {
                $voucherType = VoucherType::where('code', substr($claveAcceso, 8, 2))->first();
            }

            if ($sriData !== null) {
                Shop::create([
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
                $subTotal = (float) $valorSinImpuestos;
                $ivaAmount = (float) $iva;
                $totalAmount = (float) $total;

                Shop::create([
                    'company_id' => $companyId,
                    'contact_id' => $contact->id,
                    'voucher_type_id' => $voucherType->id,
                    'emision' => Carbon::createFromFormat('d/m/Y', trim($fechaEmision))->format('Y-m-d'),
                    'autorization' => $claveAcceso,
                    'autorized_at' => Carbon::createFromFormat('d/m/Y H:i:s', trim($fechaAutorizacion))->format('Y-m-d H:i:s'),
                    'serie' => trim($serie),
                    'sub_total' => $subTotal,
                    // TODO: No se tiene la certeza de $ivaAmount
                    'iva15' => $ivaAmount,
                    'total' => $totalAmount,
                    // TODO: Analizar los estados de los comprobantes
                    'state' => 'PENDIENTE',
                ]);
            }

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
