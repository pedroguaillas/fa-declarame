<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shop;
use Illuminate\Support\Collection;
use Normalizer;
use SimpleXMLElement;

class AtsXmlService
{
    private const BC_SCALE = 6;

    public function generate(Company $company, int $year, int $month): string
    {
        $shops = Shop::with([
            'contact.identificationType',
            'retentionItems.retention',
            'voucherType',
            'voucherTypeModify',
            'taxSupport',
        ])
            ->where('state', 'AUTORIZADO')
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

        $totalVentas = '0';

        foreach ($orders as $order) {

            $valor = bcadd($this->toStr($order->base0), $this->toStr($order->base), self::BC_SCALE);

            // NOTA DE CREDITO RESTA
            if ($order->voucher_code === '04') {
                $valor = bcmul($valor, '-1', self::BC_SCALE);
            }

            $totalVentas = bcadd($totalVentas, $valor, self::BC_SCALE);
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

        // COMPRAS
        if ($shops->count() > 0) {
            $compras = $xml->addChild('compras');

            foreach ($shops as $shop) {
                $this->addDetalleCompra($compras, $shop);
            }
        }

        // VENTAS
        if ($orders->count() > 0) {
            $ventas = $xml->addChild('ventas');
            $this->addDetalleVentas($ventas, $orders);
        }

        // VENTAS ESTABLECIMIENTO
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

    // DETALLE COMPRA
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

        // SERIE
        $serieParts = explode('-', $shop->serie ?? '');

        $d->addChild('establecimiento', $serieParts[0] ?? '001');
        $d->addChild('puntoEmision', $serieParts[1] ?? '001');
        $d->addChild('secuencial', ltrim($serieParts[2] ?? '000000001', '0'));
        $d->addChild('fechaEmision', $this->formatDate($shop->emision));
        $d->addChild('autorizacion', $shop->autorization ?? '');

        // BASES
        $d->addChild('baseNoGraIva', $this->fmt($shop->no_iva));
        $d->addChild('baseImponible', $this->fmt($shop->base0));

        $baseImpGrav = bcadd(
            bcadd($this->toStr($shop->base5), $this->toStr($shop->base8), self::BC_SCALE),
            bcadd($this->toStr($shop->base12), $this->toStr($shop->base15), self::BC_SCALE),
            self::BC_SCALE
        );

        $d->addChild('baseImpGrav', $this->fmt($baseImpGrav));
        $d->addChild('baseImpExe', $this->fmt($shop->exempt ?? 0));
        $d->addChild('montoIce', $this->fmt($shop->ice));

        $montoIva = bcadd(
            bcadd($this->toStr($shop->iva5), $this->toStr($shop->iva8), self::BC_SCALE),
            bcadd($this->toStr($shop->iva12), $this->toStr($shop->iva15), self::BC_SCALE),
            self::BC_SCALE
        );

        $d->addChild('montoIva', $this->fmt($montoIva));

        // RETENCIONES IVA
        $ivaItems = $shop->retentionItems->filter(fn ($i) => $i->retention?->type === 'IVA');

        $d->addChild('valRetBien10', $this->sumByPct($ivaItems, 10));
        $d->addChild('valRetServ20', $this->sumByPct($ivaItems, 20));
        $d->addChild('valorRetBienes', $this->sumByPct($ivaItems, 30));
        $d->addChild('valRetServ50', $this->sumByPct($ivaItems, 50));
        $d->addChild('valorRetServicios', $this->sumByPct($ivaItems, 70));
        $d->addChild('valRetServ100', $this->sumByPct($ivaItems, 100));
        $d->addChild('valorRetencionNc', '0.00');
        $d->addChild('totbasesImpReemb', '0.00');

        // PAGO EXTERIOR
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
        // FORMAS PAGO
        if (bccomp($this->toStr($shop->total), '500', self::BC_SCALE) > 0) {
            $formas = $d->addChild('formasDePago');
            $formas->addChild('formaPago', '20');
        }

        // AIR
        $rentaItems = $shop->retentionItems->filter(fn ($i) => $i->retention?->type === 'RENTA');

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

        // RETENCION
        if ($shop->serie_retention) {
            $serieRet = explode('-', $shop->serie_retention);

            $d->addChild('estabRetencion1', $serieRet[0] ?? '001');
            $d->addChild('ptoEmiRetencion1', $serieRet[1] ?? '001');
            $d->addChild('secRetencion1', $serieRet[2] ?? '000000001');
            $d->addChild('autRetencion1', $shop->autorization_retention ?? '');

            if ($shop->date_retention) {
                $d->addChild('fechaEmiRet1', $this->formatDate($shop->date_retention));
            }
        }
    }

    // DETALLE VENTAS
    private function addDetalleVentas(SimpleXMLElement $ventas, Collection $orders): void
    {
        foreach ($orders as $order) {
            $d = $ventas->addChild('detalleVentas');

            $d->addChild('tpIdCliente', $order->identification_code);
            $d->addChild('idCliente', $order->identification);

            // TODO venta consumidor final
            if ($order->identification_code !== '07') {
                $d->addChild('parteRelVtas', 'NO');
            }

            // TODO: en contactos aumentar para el tipo de pasaporte, recuperar al cliente aki no sobrecargar la bd
            if ($order->identification_code === '06') {
                $d->addChild('tipoCliente', '03');
                $d->addChild('denoCli', 'PERSONA EXTRANJERA');
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

            // FORMAS DE PAGO EXEPTO NOTAS CREDITO
            if ($order->voucher_code !== '04') {
                $formas = $d->addChild('formasDePago');
                $formas->addChild('formaPago', '20');
            }
        }
    }

    // VENTAS ESTABLECIMIENTO
    private function addVentasEstablecimiento(SimpleXMLElement $ventasEst, Collection $orders): void
    {
        $sum = '0';

        foreach ($orders as $order) {

            $valor = bcadd($this->toStr($order->base0), $this->toStr($order->base), self::BC_SCALE);

            // NOTA CRÉDITO RESTA
            if ($order->voucher_code === '04') {
                $valor = bcmul($valor, '-1', self::BC_SCALE);
            }

            $sum = bcadd($sum, $valor, self::BC_SCALE);
        }

        $ve = $ventasEst->addChild('ventaEst');

        $ve->addChild('codEstab', '001');
        $ve->addChild('ventasEstab', $this->fmt($sum));
        $ve->addChild('ivaComp', '0.00');
    }

    // HELPERS
    private function formatDate(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y');
        }

        return (string) $date;
    }

    private function normalize(string $text): string
    {
        // Descompone caracteres unicode (letra + acento separados)
        $text = Normalizer::normalize($text, Normalizer::FORM_D);

        // Elimina los acentos y diacríticos
        $text = preg_replace('/\p{Mn}/u', '', $text);

        // Solo permite letras y números, todo lo demás se elimina
        $text = preg_replace('/[^a-zA-Z0-9]/u', '', $text);

        return trim($text);
    }

    private function fmt(mixed $value): string
    {
        return number_format((float) bcadd($this->toStr($value), '0', 2), 2, '.', '');
    }

    private function sumByPct(Collection $items, float $pct): string
    {
        $sum = '0';
        foreach ($items->filter(fn ($i) => bccomp($this->toStr($i->percentage), (string) $pct, self::BC_SCALE) === 0) as $item) {
            $sum = bcadd($sum, $this->toStr($item->value), self::BC_SCALE);
        }

        return $this->fmt($sum);
    }

    private function toStr(mixed $value): string
    {
        return (string) ($value ?? '0');
    }
}
