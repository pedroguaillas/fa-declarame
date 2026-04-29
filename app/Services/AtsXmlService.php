<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shop;
use SimpleXMLElement;

class AtsXmlService
{
    public function generate(
        Company $company,
        int $year,
        int $month
    ): string {

        $shops = Shop::with([
            'contact.identificationType',
            'retentionItems.retention',
            'voucherType',
            'voucherTypeModify',
            'taxSupport',
        ])
            ->where('company_id', $company->id)
            ->whereYear('emision', $year)
            ->whereMonth('emision', $month)
            ->orderBy('emision')
            ->get();

        $orders = Order::with([
            'contact.identificationType',
            'retentionItems.retention',
            'voucherType',
        ])
            ->where('company_id', $company->id)
            ->whereYear('emision', $year)
            ->whereMonth('emision', $month)
            ->get();

        $totalVentas = $orders->sum(
            fn($o) => (float) ($o->total ?? 0)
        );

        $xml = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><iva/>'
        );

        $xml->addChild('TipoIDInformante', 'R');
        $xml->addChild('IdInformante', $company->ruc);
        $xml->addChild(
            'razonSocial',
            $this->normalize($company->name)
        );

        $xml->addChild(
            'Anio',
            (string) $year
        );

        $xml->addChild(
            'Mes',
            str_pad(
                (string) $month,
                2,
                '0',
                STR_PAD_LEFT
            )
        );

        $xml->addChild('numEstabRuc', '001');

        $xml->addChild(
            'totalVentas',
            $this->fmt($totalVentas)
        );

        $xml->addChild(
            'codigoOperativo',
            'IVA'
        );

        // ─────────────────────────────
        // COMPRAS
        // ─────────────────────────────

        if ($shops->count() > 0) {

            $compras = $xml->addChild(
                'compras'
            );

            foreach ($shops as $shop) {

                $this->addDetalleCompra(
                    $compras,
                    $shop
                );
            }
        }

        // ─────────────────────────────
        // VENTAS
        // ─────────────────────────────

        if ($orders->count() > 0) {

            $ventas = $xml->addChild(
                'ventas'
            );

            $this->addDetalleVentas(
                $ventas,
                $orders
            );
        }

        // ─────────────────────────────
        // VENTAS ESTABLECIMIENTO
        // ─────────────────────────────

        if ($orders->count() > 0) {

            $ventasEst = $xml->addChild(
                'ventasEstablecimiento'
            );

            $this->addVentasEstablecimiento(
                $ventasEst,
                $orders
            );
        }

        $dom = new \DOMDocument(
            '1.0',
            'UTF-8'
        );

        $dom->preserveWhiteSpace = false;

        $dom->formatOutput = true;

        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    // ─────────────────────────────────
    // DETALLE COMPRA
    // ─────────────────────────────────

    private function addDetalleCompra(
        SimpleXMLElement $compras,
        Shop $shop
    ): void {

        $contact = $shop->contact;

        $d = $compras->addChild(
            'detalleCompras'
        );

        $d->addChild(
            'codSustento',
            $shop->taxSupport?->code ?? '01'
        );

        $idTypeCode =
            $contact?->identificationType?->code_shop ?? '04';

        $d->addChild(
            'tpIdProv',
            $idTypeCode
        );

        $d->addChild(
            'idProv',
            $contact?->identification ?? ''
        );

        $voucherCode =
            $shop->voucherType?->code ?? '01';

        $d->addChild(
            'tipoComprobante',
            $voucherCode
        );

        $d->addChild(
            'tipoProv',
            $contact?->provider_type ?? '01'
        );

        $d->addChild(
            'denoProv',
            $this->normalize(
                $contact?->name ?? ''
            )
        );

        $d->addChild(
            'parteRel',
            'NO'
        );

        $d->addChild(
            'fechaRegistro',
            $this->formatDate(
                $shop->emision
            )
        );

        // ─────────────────────────────
        // SERIE
        // ─────────────────────────────

        $serieParts = explode(
            '-',
            $shop->serie ?? ''
        );

        $d->addChild(
            'establecimiento',
            $serieParts[0] ?? '001'
        );

        $d->addChild(
            'puntoEmision',
            $serieParts[1] ?? '001'
        );

        $d->addChild(
            'secuencial',
            ltrim(
                $serieParts[2] ?? '000000001',
                '0'
            )
        );

        $d->addChild(
            'fechaEmision',
            $this->formatDate(
                $shop->emision
            )
        );

        $d->addChild(
            'autorizacion',
            $shop->autorization ?? ''
        );

        // ─────────────────────────────
        // BASES
        // ─────────────────────────────

        $d->addChild(
            'baseNoGraIva',
            $this->fmt($shop->no_iva)
        );

        $d->addChild(
            'baseImponible',
            $this->fmt($shop->base0)
        );

        $baseImpGrav =
            (float) ($shop->base5 ?? 0)
            + (float) ($shop->base8 ?? 0)
            + (float) ($shop->base12 ?? 0)
            + (float) ($shop->base15 ?? 0);

        $d->addChild(
            'baseImpGrav',
            $this->fmt($baseImpGrav)
        );

        $d->addChild(
            'baseImpExe',
            $this->fmt($shop->exempt ?? 0)
        );

        $d->addChild(
            'montoIce',
            $this->fmt($shop->ice)
        );

        $montoIva =
            (float) ($shop->iva5 ?? 0)
            + (float) ($shop->iva8 ?? 0)
            + (float) ($shop->iva12 ?? 0)
            + (float) ($shop->iva15 ?? 0);

        $d->addChild(
            'montoIva',
            $this->fmt($montoIva)
        );

        // ─────────────────────────────
        // RETENCIONES IVA
        // ─────────────────────────────

        $ivaItems = $shop->retentionItems
            ->filter(
                fn($i) =>
                $i->retention?->type === 'IVA'
            );

        $d->addChild(
            'valRetBien10',
            $this->sumByPct($ivaItems, 10)
        );

        $d->addChild(
            'valRetServ20',
            $this->sumByPct($ivaItems, 20)
        );

        $d->addChild(
            'valorRetBienes',
            $this->sumByPct($ivaItems, 30)
        );

        $d->addChild(
            'valRetServ50',
            $this->sumByPct($ivaItems, 50)
        );

        $d->addChild(
            'valorRetServicios',
            $this->sumByPct($ivaItems, 70)
        );

        $d->addChild(
            'valRetServ100',
            $this->sumByPct($ivaItems, 100)
        );

        $d->addChild(
            'valorRetencionNc',
            '0.00'
        );

        $d->addChild(
            'totbasesImpReemb',
            '0.00'
        );

        // ─────────────────────────────
        // PAGO EXTERIOR
        // ─────────────────────────────

        $pagoExt = $d->addChild(
            'pagoExterior'
        );

        $pagoExt->addChild(
            'pagoLocExt',
            '01'
        );

        $pagoExt->addChild(
            'paisEfecPago',
            'NA'
        );

        $pagoExt->addChild(
            'aplicConvDobTrib',
            'NA'
        );

        $pagoExt->addChild(
            'pagExtSujRetNorLeg',
            'NA'
        );

        // ─────────────────────────────
        // DOCUMENTO MODIFICADO
        // SOLO NC Y ND
        // ─────────────────────────────

        if (
            in_array(
                $voucherCode,
                ['04', '05']
            )
        ) {

            if (
                $shop->voucher_type_modify_id &&
                $shop->est_modify &&
                $shop->poi_modify &&
                $shop->sec_modify &&
                $shop->aut_modify
            ) {

                $tipoModificado =
                    $shop->voucherTypeModify?->code ?? '01';

                $d->addChild(
                    'docModificado',
                    $tipoModificado
                );

                $d->addChild(
                    'estabModificado',
                    $shop->est_modify
                );

                $d->addChild(
                    'ptoEmiModificado',
                    $shop->poi_modify
                );

                $d->addChild(
                    'secModificado',
                    ltrim(
                        $shop->sec_modify,
                        '0'
                    )
                );

                $d->addChild(
                    'autModificado',
                    $shop->aut_modify
                );
            }
        }

        // ─────────────────────────────
        // FORMAS PAGO
        // ─────────────────────────────

        if (
            (float) ($shop->total ?? 0) > 500
        ) {

            $formas = $d->addChild(
                'formasDePago'
            );

            $formas->addChild(
                'formaPago',
                '20'
            );
        }

        // ─────────────────────────────
        // AIR
        // ─────────────────────────────

        $rentaItems = $shop->retentionItems
            ->filter(
                fn($i) =>
                $i->retention?->type === 'RENTA'
            );

        if (
            $rentaItems->isNotEmpty()
        ) {

            $air = $d->addChild('air');

            foreach (
                $rentaItems as $item
            ) {

                $da = $air->addChild(
                    'detalleAir'
                );

                $da->addChild(
                    'codRetAir',
                    $item->retention->code ?? ''
                );

                $da->addChild(
                    'baseImpAir',
                    $this->fmt($item->base)
                );

                $da->addChild(
                    'porcentajeAir',
                    $this->fmt(
                        $item->percentage
                    )
                );

                $da->addChild(
                    'valRetAir',
                    $this->fmt($item->value)
                );
            }
        }

        // ─────────────────────────────
        // RETENCION
        // ─────────────────────────────

        if ($shop->serie_retention) {

            $serieRet = explode(
                '-',
                $shop->serie_retention
            );

            $d->addChild(
                'estabRetencion1',
                $serieRet[0] ?? '001'
            );

            $d->addChild(
                'ptoEmiRetencion1',
                $serieRet[1] ?? '001'
            );

            $d->addChild(
                'secRetencion1',
                $serieRet[2] ?? '000000001'
            );

            $d->addChild(
                'autRetencion1',
                $shop->autorization_retention ?? ''
            );

            if (
                $shop->date_retention
            ) {

                $d->addChild(
                    'fechaEmiRet1',
                    $this->formatDate(
                        $shop->date_retention
                    )
                );
            }
        }
    }

    // ─────────────────────────────────
    // DETALLE VENTAS
    // ─────────────────────────────────

    private function addDetalleVentas(
        SimpleXMLElement $ventas,
        \Illuminate\Support\Collection $orders
    ): void {

        $grouped = $orders->groupBy(
            function (Order $order) {

                $voucherCode =
                    $order->voucherType?->code ?? '01';

                $idTypeCode =
                    $order->contact?->identificationType?->code_order ?? '04';

                $identification =
                    $order->contact?->identification ?? '';

                return "{$voucherCode}|{$idTypeCode}|{$identification}";
            }
        );

        foreach ($grouped as $key => $group) {

            [
                $voucherCode,
                $idTypeCode,
                $identification
            ] = explode('|', $key, 3);

            $d = $ventas->addChild(
                'detalleVentas'
            );

            $d->addChild(
                'tpIdCliente',
                $idTypeCode
            );

            $d->addChild(
                'idCliente',
                $identification
            );

            $d->addChild(
                'parteRelVtas',
                'NO'
            );

            $d->addChild(
                'tipoComprobante',
                $voucherCode
            );

            $d->addChild(
                'tipoEmision',
                'F'
            );

            $d->addChild(
                'numeroComprobantes',
                (string) $group->count()
            );

            $d->addChild(
                'baseNoGraIva',
                $this->fmt(
                    $group->sum(
                        fn($o) =>
                        (float) ($o->no_iva ?? 0)
                    )
                )
            );

            $d->addChild(
                'baseImponible',
                $this->fmt(
                    $group->sum(
                        fn($o) =>
                        (float) ($o->base0 ?? 0)
                    )
                )
            );

            $baseImpGrav = $group->sum(
                fn($o) =>
                (float) ($o->base5 ?? 0)
                    + (float) ($o->base8 ?? 0)
                    + (float) ($o->base12 ?? 0)
                    + (float) ($o->base15 ?? 0)
            );

            $d->addChild(
                'baseImpGrav',
                $this->fmt($baseImpGrav)
            );

            $montoIva = $group->sum(
                fn($o) =>
                (float) ($o->iva5 ?? 0)
                    + (float) ($o->iva8 ?? 0)
                    + (float) ($o->iva12 ?? 0)
                    + (float) ($o->iva15 ?? 0)
            );

            $d->addChild(
                'montoIva',
                $this->fmt($montoIva)
            );

            $d->addChild(
                'montoIce',
                $this->fmt(
                    $group->sum(
                        fn($o) =>
                        (float) ($o->ice ?? 0)
                    )
                )
            );

            $formas = $d->addChild(
                'formasDePago'
            );

            $formas->addChild(
                'formaPago',
                '20'
            );
        }
    }

    // ─────────────────────────────────
    // VENTAS ESTABLECIMIENTO
    // ─────────────────────────────────

    private function addVentasEstablecimiento(
        SimpleXMLElement $ventasEst,
        \Illuminate\Support\Collection $orders
    ): void {

        $byEstab = $orders->groupBy(
            fn(Order $o) =>
            explode(
                '-',
                $o->serie ?? ''
            )[0] ?? '001'
        );

        foreach (
            $byEstab as $codEstab => $group
        ) {

            $ve = $ventasEst->addChild(
                'ventaEst'
            );

            $ve->addChild(
                'codEstab',
                $codEstab
            );

            $ve->addChild(
                'ventasEstab',
                $this->fmt(
                    $group->sum(
                        fn($o) =>
                        (float) ($o->total ?? 0)
                    )
                )
            );

            $ivaComp = $group->sum(
                fn($o) =>
                (float) ($o->iva5 ?? 0)
                    + (float) ($o->iva8 ?? 0)
                    + (float) ($o->iva12 ?? 0)
                    + (float) ($o->iva15 ?? 0)
            );

            $ve->addChild(
                'ivaComp',
                $this->fmt($ivaComp)
            );
        }
    }

    // ─────────────────────────────────
    // HELPERS
    // ─────────────────────────────────

    private function formatDate(
        mixed $date
    ): string {

        if (!$date) {
            return '';
        }

        if (
            $date instanceof \DateTimeInterface
        ) {
            return $date->format('d/m/Y');
        }

        return (string) $date;
    }

    private function normalize(
        string $text
    ): string {

        $from = ['Á', 'É', 'Í', 'Ó', 'Ú', 'á', 'é', 'í', 'ó', 'ú', 'Ñ', 'ñ', '&', '"', "'", '`'];

        $to = ['A', 'E', 'I', 'O', 'U', 'a', 'e', 'i', 'o', 'u', 'N', 'n', ' ', ' ', ' ', ' '];

        return str_replace(
            $from,
            $to,
            $text
        );
    }

    private function fmt(
        mixed $value
    ): string {

        return number_format(
            (float) ($value ?? 0),
            2,
            '.',
            ''
        );
    }

    private function sumByPct(
        \Illuminate\Support\Collection $items,
        float $pct
    ): string {

        return $this->fmt(
            $items
                ->filter(
                    fn($i) =>
                    (float) $i->percentage === $pct
                )
                ->sum('value')
        );
    }
}
