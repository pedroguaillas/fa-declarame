<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Retention;
use App\Models\Tenant\Shop;
use App\Models\Tenant\ShopRetentionItem;
use App\Models\Tenant\TaxSupport;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;
use SimpleXMLElement;

class AtsXmlImportService
{
    private const BC_SCALE = 6;

    public function __construct(private readonly SriResolveNameService $sriResolveNameService) {}

    /** @var array<string, int|null> */
    private array $taxSupportCache = [];

    /** @var array<string, int|null> */
    private array $voucherTypeCache = [];

    /** @var array<string, int|null> */
    private array $idTypeCache = [];

    /** @var array<string, int|null> */
    private array $retentionCache = [];

    private const IVA_REFORM_DATE = '2024-04-01';

    /**
     * @return array{imported: int, skipped: int, errors: int}
     *
     * @throws \RuntimeException when IdInformante does not match the company RUC
     */
    public function import(string $xmlContent, int $companyId, string $companyRuc): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        try {
            $xml = new SimpleXMLElement($xmlContent);
        } catch (\Exception) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 1];
        }

        $idInformante = trim((string) $xml->IdInformante);
        if ($idInformante !== $companyRuc) {
            throw new \RuntimeException(
                "El RUC del archivo ATS ({$idInformante}) no coincide con el contribuyente seleccionado ({$companyRuc})."
            );
        }

        if (! isset($xml->compras->detalleCompras)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        }

        foreach ($xml->compras->detalleCompras as $detalle) {
            $autorizacion = trim((string) $detalle->autorizacion);

            if (empty($autorizacion) || $this->isDuplicate($detalle, $autorizacion, $companyId)) {
                $skipped++;

                continue;
            }

            try {
                $this->processDetalleCompra($detalle, $companyId);
                $imported++;
            } catch (\Exception) {
                $errors++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Determina si la compra ya existe en la BD.
     *
     * - Clave de acceso (49 dígitos): globalmente única → basta con autorization.
     * - Autorización antigua (10 dígitos): puede repetirse entre proveedores → se
     *   verifica además por serie y empresa para evitar falsos positivos.
     */
    private function isDuplicate(SimpleXMLElement $d, string $autorizacion, int $companyId): bool
    {
        if (strlen($autorizacion) === 49) {
            return Shop::where('autorization', $autorizacion)->exists();
        }

        if (strlen($autorizacion) === 10) {
            $establecimiento = str_pad(trim((string) $d->establecimiento), 3, '0', STR_PAD_LEFT);
            $puntoEmision = str_pad(trim((string) $d->puntoEmision), 3, '0', STR_PAD_LEFT);
            $secuencial = str_pad(trim((string) $d->secuencial), 9, '0', STR_PAD_LEFT);
            $serie = "{$establecimiento}-{$puntoEmision}-{$secuencial}";

            $fechaEmision = trim((string) $d->fechaEmision);
            $emision = null;
            try {
                $emision = Carbon::createFromFormat('d/m/Y', $fechaEmision)->format('Y-m-d');
            } catch (\Exception) {
                // sin fecha no se puede garantizar unicidad
                return true;
            }

            return Shop::where('autorization', $autorizacion)
                ->where('serie', $serie)
                ->whereDate('emision', $emision)
                ->exists();
        }

        // Longitud inesperada: no se puede determinar unicidad, omitir
        return true;
    }

    private function processDetalleCompra(SimpleXMLElement $d, int $companyId): void
    {
        $tpIdProv = trim((string) $d->tpIdProv);
        $idProv = trim((string) $d->idProv);
        $denoProv = trim((string) $d->denoProv);
        $tipoProv = trim((string) $d->tipoProv) ?: '01';
        $codSustento = trim((string) $d->codSustento) ?: '01';
        $tipoComprobante = trim((string) $d->tipoComprobante) ?: '01';
        $fechaEmision = trim((string) $d->fechaEmision);
        $autorizacion = trim((string) $d->autorizacion);

        $establecimiento = str_pad(trim((string) $d->establecimiento), 3, '0', STR_PAD_LEFT);
        $puntoEmision = str_pad(trim((string) $d->puntoEmision), 3, '0', STR_PAD_LEFT);
        $secuencial = str_pad(trim((string) $d->secuencial), 9, '0', STR_PAD_LEFT);
        $serie = "{$establecimiento}-{$puntoEmision}-{$secuencial}";

        $baseNoGraIva = (float) $d->baseNoGraIva;
        $baseImponible = (float) $d->baseImponible;
        $baseImpGrav = (float) $d->baseImpGrav;
        $baseImpExe = (float) $d->baseImpExe;
        $montoIce = (float) $d->montoIce;
        $montoIva = (float) $d->montoIva;

        $emision = null;
        $emisionDate = null;
        if ($fechaEmision) {
            try {
                $emisionDate = Carbon::createFromFormat('d/m/Y', $fechaEmision);
                $emision = $emisionDate->format('Y-m-d');
            } catch (\Exception) {
                // leave null
            }
        }

        [$baseField, $ivaField] = $this->detectIvaBucket($baseImpGrav, $montoIva, $emisionDate);

        $contact = $this->resolveContact($tpIdProv, $idProv, $denoProv, $tipoProv);

        $taxSupportId = $this->taxSupportCache[$codSustento] ??= TaxSupport::where('code', $codSustento)->value('id');
        $voucherTypeId = $this->voucherTypeCache[$tipoComprobante] ??= VoucherType::where('code', $tipoComprobante)->value('id');

        $total = $baseNoGraIva + $baseImponible + $baseImpGrav + $baseImpExe + $montoIce + $montoIva;

        $shopData = [
            'company_id' => $companyId,
            'contact_id' => $contact?->id,
            'voucher_type_id' => $voucherTypeId,
            'tax_support_id' => $taxSupportId,
            'emision' => $emision,
            'autorization' => $autorizacion,
            'serie' => $serie,
            'no_iva' => $baseNoGraIva,
            'base0' => $baseImponible,
            $baseField => $baseImpGrav,
            $ivaField => $montoIva,
            'ice' => $montoIce,
            'total' => $total,
            'state' => 'AUTORIZADO',
        ];

        // Documento modificado (notas de crédito/débito)
        $docModificado = trim((string) $d->docModificado);
        if ($docModificado) {
            $shopData['voucher_type_modify_id'] = $this->voucherTypeCache[$docModificado] ??= VoucherType::where('code', $docModificado)->value('id');
            $shopData['est_modify'] = (int) $d->estabModificado;
            $shopData['poi_modify'] = (int) $d->ptoEmiModificado;
            $shopData['sec_modify'] = ltrim((string) $d->secModificado, '0') ?: '0';
            $shopData['aut_modify'] = trim((string) $d->autModificado);
        }

        // Retención emitida
        $estabRetencion = trim((string) $d->estabRetencion1);
        if ($estabRetencion) {
            $ptoRet = trim((string) $d->ptoEmiRetencion1);
            $secRet = trim((string) $d->secRetencion1);
            $shopData['serie_retention'] = "{$estabRetencion}-{$ptoRet}-{$secRet}";
            $shopData['autorization_retention'] = trim((string) $d->autRetencion1);
            $shopData['state_retention'] = 'AUTORIZADO';

            $fechaEmiRet = trim((string) $d->fechaEmiRet1);
            try {
                $shopData['date_retention'] = $fechaEmiRet
                    ? Carbon::createFromFormat('d/m/Y', $fechaEmiRet)->format('Y-m-d')
                    : $emision;
            } catch (\Exception) {
                $shopData['date_retention'] = $emision;
            }
        } elseif ($this->hasCode332($d)) {
            // Código 332x: agente de retención obligado que no generó retención efectiva.
            // El ATS no incluye las etiquetas estabRetencion1; se usan valores convencionales.
            $shopData['serie_retention'] = '999-999-999';
            $shopData['autorization_retention'] = '9999999999';
            $shopData['state_retention'] = 'AUTORIZADO';
            $shopData['date_retention'] = $emision;
        }

        $shop = Shop::create($shopData);

        // Retenciones IVA
        $ivaMap = [
            'valRetBien10' => 10,
            'valRetServ20' => 20,
            'valorRetBienes' => 30,
            'valRetServ50' => 50,
            'valorRetServicios' => 70,
            'valRetServ100' => 100,
        ];

        foreach ($ivaMap as $xmlField => $pct) {
            $value = (float) $d->{$xmlField};

            if ($value <= 0) {
                continue;
            }

            $retentionId = $this->findRetentionId('IVA', (string) $pct);

            if ($retentionId) {
                ShopRetentionItem::create([
                    'shop_id' => $shop->id,
                    'retention_id' => $retentionId,
                    'base' => round($value * 100 / $pct, 2),
                    'percentage' => $pct,
                    'value' => $value,
                ]);
            }
        }

        // Retenciones RENTA (AIR)
        if (isset($d->air->detalleAir)) {
            foreach ($d->air->detalleAir as $da) {
                $codRetAir = trim((string) $da->codRetAir);

                if (empty($codRetAir)) {
                    continue;
                }

                $baseImpAir = (float) $da->baseImpAir;
                $porcentajeAir = (float) $da->porcentajeAir;
                $valRetAir = (float) $da->valRetAir;
                $retentionId = $this->findRetentionIdByCode('RENTA', $codRetAir);

                ShopRetentionItem::create([
                    'shop_id' => $shop->id,
                    'retention_id' => $retentionId,
                    'base' => $baseImpAir,
                    'percentage' => $porcentajeAir,
                    'value' => $valRetAir,
                ]);
            }
        }
    }

    /**
     * Indica si el detalle contiene el código 332 en la sección AIR.
     * Código 332: comprobante de retención emitido sin valor efectivo (base y valor cero).
     */
    private function hasCode332(SimpleXMLElement $d): bool
    {
        if (! isset($d->air->detalleAir)) {
            return false;
        }

        foreach ($d->air->detalleAir as $da) {
            if (str_starts_with(trim((string) $da->codRetAir), '332')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns [baseField, ivaField] based on detected IVA rate and emission date.
     *
     * Before 2024-04-01 only IVA 12% existed.
     * From 2024-04-01 onwards IVA 12% was replaced by 5%, 8% and 15%.
     *
     * @return array{string, string}
     */
    private function detectIvaBucket(float $base, float $iva, ?Carbon $emisionDate): array
    {
        $beforeReform = $emisionDate === null || $emisionDate->lt(Carbon::parse(self::IVA_REFORM_DATE));

        if ($beforeReform) {
            return ['base12', 'iva12'];
        }

        if ($base <= 0) {
            return ['base15', 'iva15'];
        }

        $rate = round($iva / $base, 2);

        return match ($rate) {
            0.05 => ['base5', 'iva5'],
            0.08 => ['base8', 'iva8'],
            default => ['base15', 'iva15'],
        };
    }

    private function resolveContact(string $tpIdProv, string $idProv, string $denoProv, string $tipoProv): ?Contact
    {
        if (empty($idProv)) {
            return null;
        }

        $existing = Contact::where('identification', $idProv)->first();

        if ($existing) {
            return $existing;
        }

        if (empty($denoProv)) {
            try {
                $sriData = $this->sriResolveNameService->searchByIdentificationSRI($idProv);
                $denoProv = $sriData['name'] ?? '';
            } catch (\Throwable) {
                // Si el SRI no responde se registra sin nombre
            }
        }

        $idTypeId = $this->idTypeCache[$tpIdProv] ??= IdentificationType::where('code_shop', $tpIdProv)->value('id');

        return Contact::create([
            'identification' => $idProv,
            'identification_type_id' => $idTypeId,
            'name' => $denoProv,
            'provider_type' => $tipoProv ?: '01',
        ]);
    }

    private function findRetentionId(string $type, string $percentage): ?int
    {
        $key = "{$type}_{$percentage}";

        return $this->retentionCache[$key] ??= Retention::where('type', $type)
            ->where('percentage', (float) $percentage)
            ->value('id');
    }

    private function findRetentionIdByCode(string $type, string $code): ?int
    {
        $key = "{$type}_code_{$code}";

        return $this->retentionCache[$key] ??= Retention::where('type', $type)
            ->where('code', $code)
            ->value('id');
    }
}
