<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shop;
use SimpleXMLElement;
use Illuminate\Support\Collection;

class AtsXmlService
{
    public function generate(Company $company, int $year, int $month): string
    {
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

        $orders = Order::query()
            ->join('contacts', 'contacts.id', '=', 'orders.contact_id')
            ->join('identification_types', 'identification_types.id', '=', 'contacts.identification_type_id')
            ->leftJoin('order_retention_items', 'order_retention_items.order_id', '=', 'orders.id')
            ->leftJoin('retentions', 'retentions.id', '=', 'order_retention_items.retention_id')
            ->join('voucher_types', 'voucher_types.id', '=', 'orders.voucher_type_id')
            ->where('orders.company_id', $company->id)
            ->whereYear('orders.emision', $year)
            ->whereMonth('orders.emision', $month)
            ->selectRaw("
                SUM(orders.no_iva) AS no_iva,
                SUM(orders.exempt) AS exempt,
                SUM(orders.base0) AS base0,
                SUM(orders.no_iva) AS no_iva,
                SUM(
                    orders.base5 +
                    orders.base8 +
                    orders.base12 +
                    orders.base15
                ) AS base,

                SUM(
                    orders.iva5 +
                    orders.iva8 +
                    orders.iva12 +
                    orders.iva15
                ) AS iva,

                SUM(
                    CASE
                        WHEN retentions.type = 'IVA'
                        THEN order_retention_items.value
                        ELSE 0
                    END
                ) AS retention_iva,

                SUM(
                    CASE
                        WHEN retentions.type = 'RENTA'
                        THEN order_retention_items.value
                        ELSE 0
                    END
                ) AS retention_renta,

                COUNT(*) as num_compr,
                contacts.identification,
                identification_types.code_order AS identification_code,
                voucher_types.code AS voucher_code
            ")
            ->groupBy(
                'contacts.identification',
                'identification_types.code_order',
                'voucher_types.code'
            )
            ->get();

        $totalVentas = 0;

        foreach ($orders as $order) {

            $valor = (float) ($order->base0 ?? 0) + (float) ($order->base ?? 0);

            // NOTA DE CREDITO RESTA

            if ($order->voucher_code === '04') {
                $valor *= -1;
            }

            $totalVentas += $valor;
        }

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><iva/>');

        $xml->addChild('TipoIDInformante', 'R');
        $xml->addChild('IdInformante', $company->ruc);
        $xml->addChild('razonSocial', $this->normalize($company->name));

        $xml->addChild('Anio', (string) $year);

        $xml->addChild('Mes', str_pad((string) $month, 2, '0', STR_PAD_LEFT));

        $xml->addChild('numEstabRuc', '001');

        $xml->addChild('totalVentas', $this->fmt($totalVentas));

        $xml->addChild('codigoOperativo', 'IVA');

        // ─────────────────────────────
        // COMPRAS
        // ─────────────────────────────

        if ($shops->count() > 0) {

            $compras = $xml->addChild('compras');

            foreach ($shops as $shop) {

                $this->addDetalleCompra($compras, $shop);
            }
        }

        // ─────────────────────────────
        // VENTAS
        // ─────────────────────────────

        if ($orders->count() > 0) {

            $ventas = $xml->addChild('ventas');

            $this->addDetalleVentas($ventas, $orders);
        }

        // ─────────────────────────────
        // VENTAS ESTABLECIMIENTO
        // ─────────────────────────────

        if ($orders->count() > 0) {

            $ventasEst = $xml->addChild('ventasEstablecimiento');

            $this->addVentasEstablecimiento($ventasEst, $orders);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $dom->preserveWhiteSpace = false;

        $dom->formatOutput = true;

        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    // ─────────────────────────────────
    // DETALLE COMPRA
    // ─────────────────────────────────

    private function addDetalleCompra(SimpleXMLElement $compras, Shop $shop): void
    {
        $contact = $shop->contact;

        $d = $compras->addChild('detalleCompras');

        $d->addChild('codSustento', $shop->taxSupport?->code ?? '01');

        $idTypeCode = $contact?->identificationType?->code_shop ?? '04';

        $d->addChild('tpIdProv', $idTypeCode);

        $d->addChild('idProv', $contact?->identification ?? '');

        $voucherCode = $shop->voucherType?->code ?? '01';

        $d->addChild('tipoComprobante', $voucherCode);

        $d->addChild('tipoProv', $contact?->provider_type ?? '01');

        $d->addChild('denoProv', $this->normalize($contact?->name ?? ''));

        $d->addChild('parteRel', 'NO');

        $d->addChild('fechaRegistro', $this->formatDate($shop->emision));

        // ─────────────────────────────
        // SERIE
        // ─────────────────────────────

        $serieParts = explode('-', $shop->serie ?? '');

        $d->addChild('establecimiento', $serieParts[0] ?? '001');

        $d->addChild('puntoEmision', $serieParts[1] ?? '001');

        $d->addChild('secuencial', ltrim($serieParts[2] ?? '000000001', '0'));

        $d->addChild('fechaEmision', $this->formatDate($shop->emision));

        $d->addChild('autorizacion', $shop->autorization ?? '');

        // ─────────────────────────────
        // BASES
        // ─────────────────────────────

        $d->addChild('baseNoGraIva', $this->fmt($shop->no_iva));

        $d->addChild('baseImponible', $this->fmt($shop->base0));

        $baseImpGrav = (float) ($shop->base5 ?? 0) + (float) ($shop->base8 ?? 0) + (float) ($shop->base12 ?? 0) + (float) ($shop->base15 ?? 0);

        $d->addChild('baseImpGrav', $this->fmt($baseImpGrav));

        $d->addChild('baseImpExe', $this->fmt($shop->exempt ?? 0));

        $d->addChild('montoIce', $this->fmt($shop->ice));

        $montoIva = (float) ($shop->iva5 ?? 0) + (float) ($shop->iva8 ?? 0) + (float) ($shop->iva12 ?? 0) + (float) ($shop->iva15 ?? 0);

        $d->addChild('montoIva', $this->fmt($montoIva));

        // ─────────────────────────────
        // RETENCIONES IVA
        // ─────────────────────────────

        $ivaItems = $shop->retentionItems->filter(fn($i) => $i->retention?->type === 'IVA');

        $d->addChild('valRetBien10', $this->sumByPct($ivaItems, 10));

        $d->addChild('valRetServ20', $this->sumByPct($ivaItems, 20));

        $d->addChild('valorRetBienes', $this->sumByPct($ivaItems, 30));

        $d->addChild('valRetServ50', $this->sumByPct($ivaItems, 50));

        $d->addChild('valorRetServicios', $this->sumByPct($ivaItems, 70));

        $d->addChild('valRetServ100', $this->sumByPct($ivaItems, 100));

        $d->addChild('valorRetencionNc', '0.00');

        $d->addChild('totbasesImpReemb', '0.00');

        // ─────────────────────────────
        // PAGO EXTERIOR
        // ─────────────────────────────

        $pagoExt = $d->addChild('pagoExterior');

        $pagoExt->addChild('pagoLocExt', '01');

        $pagoExt->addChild('paisEfecPago', 'NA');

        $pagoExt->addChild('aplicConvDobTrib', 'NA');

        $pagoExt->addChild('pagExtSujRetNorLeg', 'NA');

        if (in_array($voucherCode, ['04', '05'])) {

            if (
                $shop->voucher_type_modify_id &&
                $shop->est_modify !== null &&
                $shop->poi_modify !== null &&
                $shop->sec_modify !== null &&
                $shop->aut_modify
            ) {

                $tipoModificado = $shop->voucherTypeModify?->code ?? '01';

                $d->addChild('docModificado', $tipoModificado);

                $d->addChild('estabModificado', str_pad((string) $shop->est_modify, 3, '0', STR_PAD_LEFT));

                $d->addChild('ptoEmiModificado', str_pad((string) $shop->poi_modify, 3, '0', STR_PAD_LEFT));

                $d->addChild('secModificado', str_pad((string) $shop->sec_modify, 9, '0', STR_PAD_LEFT));

                $d->addChild('autModificado', $shop->aut_modify);
            }
        }
        // ─────────────────────────────
        // FORMAS PAGO
        // ─────────────────────────────

        if ((float) ($shop->total ?? 0) > 500) {

            $formas = $d->addChild('formasDePago');

            $formas->addChild('formaPago', '20');
        }

        // ─────────────────────────────
        // AIR
        // ─────────────────────────────

        $rentaItems = $shop->retentionItems
            ->filter(fn($i) => $i->retention?->type === 'RENTA');

        if ($rentaItems->isNotEmpty()) {

            $air = $d->addChild('air');

            foreach ($rentaItems as $item) {

                $da = $air->addChild('detalleAir');

                $da->addChild('codRetAir', $item->retention->code ?? '');

                $da->addChild('baseImpAir', $this->fmt($item->base));

                $da->addChild('porcentajeAir', $this->fmt($item->percentage));

                $da->addChild('valRetAir', $this->fmt($item->value));
            }
        }

        // ─────────────────────────────
        // RETENCION
        // ─────────────────────────────

        if ($shop->serie_retention) {

            $serieRet = explode('-', $shop->serie_retention);

            $d->addChild('estabRetencion1', $serieRet[0] ?? '001');

            $d->addChild('ptoEmiRetencion1', $serieRet[1] ?? '001');

            $d->addChild('secRetencion1', $serieRet[2] ?? '000000001');

            $d->addChild('autRetencion1',  $shop->autorization_retention ?? '');

            if ($shop->date_retention) {

                $d->addChild('fechaEmiRet1', $this->formatDate($shop->date_retention));
            }
        }
    }

    // ─────────────────────────────────
    // DETALLE VENTAS
    // ─────────────────────────────────

    private function addDetalleVentas(SimpleXMLElement $ventas, Collection $orders): void
    {
        foreach ($orders as $order) {

            $d = $ventas->addChild('detalleVentas');

            $d->addChild('tpIdCliente', $order->identification_code);

            $d->addChild('idCliente', $order->identification);

            //TODO venta consumidor final
            if ($order->identification_code !== '07') {
                $d->addChild('parteRelVtas', 'NO');
            }

            //TODO  en contactos aumentar para el tipo de pasaporte null, recuperar al cliente aki no sobrecargar la bd
            if ($order->identification_code === '06') {
                $d->addChild('tipoCliente', '03');
                $d->addChild('denoCli', 'Persona Extrangera');
            }

            $type = ((int) ($order->voucher_code)) > 7 || $order->voucher_code === '01' ? '18' : $order->voucher_code;

            $d->addChild('tipoComprobante', $type);

            $d->addChild('tipoEmision', 'F');

            $d->addChild('numeroComprobantes', $order->num_compr);

            $d->addChild('baseNoGraIva', $this->fmt($order->no_iva));

            $d->addChild('baseImponible', $this->fmt($order->base0));

            $d->addChild('baseImpGrav', $this->fmt($order->base));

            $d->addChild('montoIva', $this->fmt($order->iva));

            $d->addChild('montoIce', '0.00');

            $d->addChild('valorRetIva', $this->fmt($order->retention_iva));

            $d->addChild('valorRetRenta', $this->fmt($order->retention_renta));

            // FORMAS DE PAGO
            // NO PARA NOTAS CREDITO

            if ($order->voucher_code !== '04') {

                $formas = $d->addChild('formasDePago');
                $formas->addChild('formaPago', '20');
            }
        }
    }

    // ─────────────────────────────────
    // VENTAS ESTABLECIMIENTO
    // ─────────────────────────────────

    private function addVentasEstablecimiento(SimpleXMLElement $ventasEst, Collection $orders): void
    {
        $sum = 0;

        foreach ($orders as $order) {

            $valor = (float) ($order->base0 ?? 0) + (float) ($order->base ?? 0);

            // NOTA CREDITO RESTA

            if ($order->voucher_code === '04') {
                $valor *= -1;
            }

            $sum += $valor;
        }

        $ve = $ventasEst->addChild('ventaEst');

        $ve->addChild('codEstab', '001');

        $ve->addChild('ventasEstab', $this->fmt($sum));

        $ve->addChild('ivaComp', '0.00');
    }

    // ─────────────────────────────────
    // HELPERS
    // ─────────────────────────────────

    private function formatDate(mixed $date): string
    {
        if (!$date) {
            return '';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y');
        }

        return (string) $date;
    }

    private function normalize(string $text): string
    {
        $from = ['Á', 'É', 'Í', 'Ó', 'Ú', 'á', 'é', 'í', 'ó', 'ú', 'Ñ', 'ñ', '&', '"', "'", '`'];

        $to = ['A', 'E', 'I', 'O', 'U', 'a', 'e', 'i', 'o', 'u', 'N', 'n', ' ', ' ', ' ', ' '];

        return str_replace($from, $to, $text);
    }

    private function fmt(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function sumByPct(Collection $items, float $pct): string
    {
        return $this->fmt(
            $items->filter(fn($i) => (float) $i->percentage === $pct)->sum('value')
        );
    }
}
