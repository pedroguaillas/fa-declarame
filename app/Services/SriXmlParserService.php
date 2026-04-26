<?php

namespace App\Services;

use App\Models\Tenant\ContributorType;
use App\Models\Tenant\IdentificationType;
use Carbon\Carbon;
use Constants;
use SimpleXMLElement;

class SriXmlParserService
{
    /** @var array<string, int> */
    private array $contributorTypeCache = [];

    /** @var array<string, string> */
    private array $infoNodeMap = [
        '01' => 'infoFactura',
        '03' => 'infoLiquidacionCompra',
        '04' => 'infoNotaCredito',
        '05' => 'infoNotaDebito',
    ];

    /**
     * Parse an autorizacion object returned by SriSoapService::authorize().
     *
     * @return array{
     *   estado: string,
     *   fecha_autorizacion: string,
     *   contributor_type_id: int,
     *   ruc_emisor: string,
     *   razon_social_emisor: string,
     *   nombre_comercial_emisor: string,
     *   cod_doc: string,
     *   fecha_emision: string,
     *   serie: string,
     *   identificacion_comprador: string,
     *   razon_social_comprador: string,
     *   sub_total: float,
     *   discount: float,
     *   total: float,
     *   base0: float,
     *   no_iva: float,
     *   base5: float,
     *   base8: float,
     *   base12: float,
     *   base15: float,
     *   iva5: float,
     *   iva8: float,
     *   iva12: float,
     *   iva15: float,
     * }|null
     */
    public function parse(object $autorizacion): ?array
    {
        $xmlString = $autorizacion->comprobante ?? '';

        if (empty($xmlString)) {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);
        libxml_clear_errors();

        if ($xml === false) {
            return null;
        }

        $infoTributaria = $xml->infoTributaria;
        $infoFactura = $xml->infoFactura;
        $codDoc = (string) $infoTributaria->codDoc;
        $infoNodeName = $this->infoNodeMap[$codDoc] ?? null;

        if ($infoNodeName === null || ! isset($xml->{$infoNodeName})) {
            return null;
        }

        $info = $xml->{$infoNodeName};

        $serie = sprintf(
            '%s-%s-%s',
            (string) $infoTributaria->estab,
            (string) $infoTributaria->ptoEmi,
            (string) $infoTributaria->secuencial,
        );

        return [
            'estado' => (string) ($autorizacion->estado ?? 'AUTORIZADO'),
            'fecha_autorizacion' => Carbon::parse((string) $autorizacion->fechaAutorizacion)->format('Y-m-d H:i:s'),
            'contributor_type_id' => $this->resolveContributorTypeId($infoTributaria),
            'ruc_emisor' => (string) $infoTributaria->ruc,
            'razon_social_emisor' => (string) $infoTributaria->razonSocial,
            'nombre_comercial_emisor' => (string) ($infoTributaria->nombreComercial ?? $infoTributaria->razonSocial),
            'cod_doc' => $codDoc,
            'fecha_emision' => Carbon::createFromFormat('d/m/Y', trim((string) $info->fechaEmision))->format('Y-m-d'),
            'serie' => $serie,
            'tipoIdentificacionComprador' => $this->resolveTipoIdentificacionComprador($infoFactura),
            'identificacion_comprador' => trim((string) ($info->identificacionComprador ?? '')),
            'razon_social_comprador' => trim((string) ($info->razonSocialComprador ?? '')),
            'sub_total' => (float) ($info->totalSinImpuestos ?? 0),
            'discount' => (float) ($info->totalDescuento ?? 0),
            'total' => (float) ($info->importeTotal ?? $info->valorModificacion ?? 0),
            ...$this->extractIva($info),
        ];
    }

    private function resolveTipoIdentificacionComprador(SimpleXMLElement $infoFactura): int
    {
        $tipoIdentificacionComprador = trim((string) $infoFactura->tipoIdentificacionComprador);

        return IdentificationType::where('code_order', $tipoIdentificacionComprador)->value('id');
    }

    private function resolveContributorTypeId(SimpleXMLElement $infoTributaria): int
    {
        $rimpe = trim((string) ($infoTributaria->contribuyenteRimpe ?? ''));

        if ($rimpe === 'CONTRIBUYENTE NEGOCIO POPULAR - RÉGIMEN RIMPE') {
            $description = 'RIMPE NEGOCIO POPULAR';
        } elseif ($rimpe === 'CONTRIBUYENTE RÉGIMEN RIMPE') {
            $description = 'RIMPE EMPRENDEDOR';
        } else {
            $description = 'GENERAL';
        }

        return $this->contributorTypeCache[$description]
            ??= ContributorType::where('description', $description)->value('id');
    }

    /**
     * @return array{base0: float, no_iva: float, base5: float, base8: float, base12: float, base15: float, iva5: float, iva8: float, iva12: float, iva15: float}
     */
    private function extractIva(SimpleXMLElement $info): array
    {
        $result = [
            'base0' => 0.0, 'no_iva' => 0.0,
            'base5' => 0.0, 'base8' => 0.0, 'base12' => 0.0, 'base15' => 0.0,
            'iva5' => 0.0, 'iva8' => 0.0, 'iva12' => 0.0, 'iva15' => 0.0,
        ];

        if (! isset($info->totalConImpuestos->totalImpuesto)) {
            return $result;
        }

        foreach ($info->totalConImpuestos->totalImpuesto as $impuesto) {
            // codigo 2 = IVA; skip ICE (code 3) and others
            if ((int) $impuesto->codigo !== 2) {
                continue;
            }

            $code = (int) $impuesto->codigoPorcentaje;
            $base = (float) $impuesto->baseImponible;
            $valor = (float) $impuesto->valor;

            if ($code === Constants::IVA0 || $code === Constants::IVA_EXENT0) {
                $result['base0'] += $base;
            } elseif ($code === Constants::IVA5) {
                $result['base5'] += $base;
                $result['iva5'] += $valor;
            } elseif ($code === Constants::IVA12) {
                $result['base12'] += $base;
                $result['iva12'] += $valor;
            } elseif ($code === Constants::IVA15) {
                $result['base15'] += $base;
                $result['iva15'] += $valor;
            } elseif ($code === Constants::NO_IVA) {
                $result['no_iva'] += $base;
            } elseif ($code === Constants::IVA_DIFERIDO) {
                $result['base12'] += $base;
                $result['iva12'] += $valor;
            }
        }

        return $result;
    }
}
