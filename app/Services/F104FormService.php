<?php

namespace App\Services;

use App\Models\Tenant\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calcula los casilleros del Formulario 104 (IVA) desde orders (ventas)
 * y shops (compras) del período. Bruto = documentos positivos; NETO =
 * bruto menos notas de crédito (voucher_types.code = '04').
 */
class F104FormService
{
    /** tax_supports.code con derecho a crédito tributario IVA (no activo fijo). */
    private const CREDIT_SUPPORTS = ['01', '06'];

    /** tax_supports.code de activo fijo con derecho a crédito IVA. */
    private const FIXED_ASSET_CREDIT_SUPPORT = '03';

    /**
     * @return array{sections: array<int, array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}>, unmapped: array<int, mixed>}
     */
    public function build(int $companyId, int $year, int $startMonth, int $endMonth): array
    {
        $startDate = sprintf('%d-%02d-01', $year, $startMonth);
        $endDate = Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d');

        $company = Company::withoutGlobalScopes()->find($companyId, ['id', 'ruc', 'name', 'type_declaration']);
        $isSemiannual = $company?->type_declaration === 'semestral';

        $ventas = $this->ventas($companyId, $startDate, $endDate);
        $compras = $this->compras($companyId, $startDate, $endDate);
        $retIva = $this->retencionesIva($companyId, $startDate, $endDate);

        // ── Ventas ──
        $v401 = $ventas['gravada_bruto'];
        $v425 = $ventas['base5_bruto'];
        $v403 = $ventas['base0_bruto'];
        $v411 = $ventas['gravada_neto'];
        $v435 = $ventas['base5_neto'];
        $v413 = $ventas['base0_neto'];
        $v421 = $ventas['iva_neto'];
        $v445 = $ventas['iva5_neto'];
        $v431 = $ventas['no_objeto_neto'];

        $v409 = round($v401 + $v403 + $v425, 2);
        $v419 = round($v411 + $v413 + $v435, 2);
        $v429 = round($v421 + $v445, 2);
        $v480 = round($v411 + $v435, 2);
        $v482 = $v429;
        $v484 = $v482;
        $v499 = $v484;

        // ── Compras ──
        $c500 = $compras['credito_bruto'];
        $c501 = $compras['af_bruto'];
        $c502 = $compras['sin_derecho_bruto'];
        $c507 = $compras['base0_bruto'];
        $c508 = $compras['rimpe_np_bruto'];
        $c540 = $compras['base5_bruto'];
        $c510 = $compras['credito_neto'];
        $c511 = $compras['af_neto'];
        $c512 = $compras['sin_derecho_neto'];
        $c517 = $compras['base0_neto'];
        $c518 = $compras['rimpe_np_neto'];
        $c550 = $compras['base5_neto'];
        $c520 = $compras['iva_credito_neto'];
        $c521 = $compras['iva_af_neto'];
        $c522 = $compras['iva_sin_derecho_neto'];
        $c560 = $compras['iva5_neto'];
        $c531 = $compras['no_objeto_neto'];
        $c532 = $compras['exentas_neto'];

        $c509 = round($c500 + $c501 + $c502 + $c507 + $c508 + $c540, 2);
        $c519 = round($c510 + $c511 + $c512 + $c517 + $c518 + $c550, 2);
        $c529 = round($c520 + $c521 + $c522 + $c560, 2);

        $gravadaConDerecho = $v411 + $v435;
        $c563 = $v419 > 0 ? round($gravadaConDerecho / $v419, 4) : 1.0;
        $c564 = round(($c520 + $c521 + $c560) * $c563, 2);
        $c565 = round(($c520 + $c521 + $c560) - $c564, 2);

        // ── Resumen impositivo ──
        $c601 = round(max(0, $v499 - $c564), 2);
        $c602 = round(max(0, $c564 - $v499), 2);
        $c609 = $retIva['recibidas'];
        $c615 = $c602;
        $c617 = round(max(0, $c609 - $c601), 2);
        $c620 = round(max(0, $c601 - $c609), 2);
        $c699 = $c620;

        // ── Retenciones IVA efectuadas ──
        $ret = $retIva['efectuadas'];
        $c799 = round(array_sum($ret), 2);
        $c801 = $c799;
        $c859 = round($c699 + $c801, 2);

        $sections = [
            $this->section('IDENTIFICACIÓN', [
                // Como string para mostrarse sin formato decimal en página y Excel
                ['101', 'Mes', (string) $startMonth, 'auto'],
                ['102', 'Año', (string) $year, 'auto'],
                ['104', 'Nº form. que sustituye', null, 'manual'],
                ['105', 'Nº autorización form. anterior', null, 'manual'],
                ['198', 'RUC', $company?->ruc, 'auto'],
                ['199', 'Razón social', $company?->name, 'auto'],
                ['201', 'Semestre (1=ene-jun, 2=jul-dic)', (string) ($isSemiannual ? ($startMonth <= 6 ? 1 : 2) : 0), 'auto'],
            ]),
            $this->section('VENTAS', [
                ['401', 'Ventas locales (excluye AF) CON IVA (tarifa ≠ 0) — bruto', $v401, 'auto'],
                ['402', 'Ventas activos fijos CON IVA — bruto', 0, 'manual'],
                ['403', 'Ventas locales tarifa 0% SIN derecho — bruto', $v403, 'auto'],
                ['405', 'Ventas locales tarifa 0% CON derecho — bruto', 0, 'manual'],
                ['407', 'Exportaciones de bienes', 0, 'manual'],
                ['408', 'Exportaciones de servicios y/o derechos', 0, 'manual'],
                ['409', 'Total ventas y otras operaciones — BRUTO', $v409, 'formula'],
                ['425', 'Ventas locales gravadas tarifa 5% — bruto', $v425, 'auto'],
                ['411', 'Ventas locales (excluye AF) CON IVA — NETO', $v411, 'auto'],
                ['413', 'Ventas locales tarifa 0% SIN derecho — NETO', $v413, 'auto'],
                ['419', 'Total ventas y otras operaciones — NETO', $v419, 'formula'],
                ['435', 'Ventas locales 5% — NETO', $v435, 'auto'],
                ['421', 'IVA generado ventas locales', $v421, 'auto'],
                ['429', 'Operaciones y ventas — impuesto generado', $v429, 'formula'],
                ['445', 'IVA generado ventas 5%', $v445, 'auto'],
                ['431', 'Transferencias no objeto o exentas de IVA', $v431, 'auto'],
                ['480', 'Total transferencias gravadas ≠ 0 a CONTADO este mes', $v480, 'formula'],
                ['481', 'Total transferencias gravadas ≠ 0 a CRÉDITO este mes', 0, 'manual'],
                ['482', 'Total impuesto generado', $v482, 'formula'],
                ['483', 'Impuesto a liquidar del mes ANTERIOR', 0, 'manual'],
                ['484', 'Impuesto a liquidar EN ESTE MES', $v484, 'formula'],
                ['499', 'TOTAL IMPUESTO A LIQUIDAR EN ESTE MES', $v499, 'formula'],
            ]),
            $this->section('COMPRAS', [
                ['500', 'Adquisiciones (excluye AF) CON IVA — bruto (con derecho)', $c500, 'auto'],
                ['501', 'Adquisiciones locales de AF CON IVA — bruto (con derecho)', $c501, 'auto'],
                ['502', 'Otras adquisiciones CON IVA — bruto (sin derecho)', $c502, 'auto'],
                ['503', 'Importaciones servicios gravados ≠ 0 — bruto', 0, 'manual'],
                ['504', 'Importaciones bienes (excluye AF) gravados ≠ 0 — bruto', 0, 'manual'],
                ['505', 'Importaciones AF gravados ≠ 0 — bruto', 0, 'manual'],
                ['507', 'Adquisiciones (incluye AF) gravadas tarifa 0% — bruto', $c507, 'auto'],
                ['508', 'Adquisiciones a RISE/RIMPE Negocios Populares — bruto', $c508, 'auto'],
                ['509', 'TOTAL ADQUISICIONES Y PAGOS — BRUTO', $c509, 'formula'],
                ['540', 'Adquisiciones locales tarifa 5% (CON derecho) — bruto', $c540, 'auto'],
                ['510', 'Adquisiciones (excluye AF) CON IVA — NETO (con derecho)', $c510, 'auto'],
                ['511', 'Adquisiciones locales de AF CON IVA — NETO (con derecho)', $c511, 'auto'],
                ['512', 'Otras adquisiciones CON IVA — NETO (sin derecho)', $c512, 'auto'],
                ['517', 'Adquisiciones 0% — NETO', $c517, 'auto'],
                ['518', 'RISE/RIMPE Negocios Populares — NETO', $c518, 'auto'],
                ['519', 'TOTAL ADQUISICIONES Y PAGOS — NETO', $c519, 'formula'],
                ['550', 'Adquisiciones 5% — NETO', $c550, 'auto'],
                ['520', 'IVA generado adquisiciones (excluye AF) — con derecho', $c520, 'auto'],
                ['521', 'IVA generado adquisiciones locales de AF — con derecho', $c521, 'auto'],
                ['522', 'IVA generado otras adquisiciones — sin derecho', $c522, 'auto'],
                ['523', 'IVA generado importaciones servicios', 0, 'manual'],
                ['524', 'IVA generado importaciones bienes', 0, 'manual'],
                ['525', 'IVA generado importaciones AF', 0, 'manual'],
                ['529', 'TOTAL IMPUESTO GENERADO COMPRAS', $c529, 'formula'],
                ['560', 'IVA generado adquisiciones 5%', $c560, 'auto'],
                ['531', 'Adquisiciones no objeto de IVA — bruto', $c531, 'auto'],
                ['532', 'Adquisiciones exentas del pago de IVA — bruto', $c532, 'auto'],
                ['563', 'Factor de proporcionalidad para crédito tributario', $c563, 'formula'],
                ['564', 'Crédito tributario aplicable en este período', $c564, 'formula'],
                ['565', 'Valor IVA NO considerado crédito por factor', $c565, 'formula'],
            ]),
            $this->section('RESUMEN IMPOSITIVO', [
                ['601', 'Impuesto causado', $c601, 'formula'],
                ['602', 'Crédito tributario aplicable este período', $c602, 'formula'],
                ['605', 'Saldo crédito mes anterior por adquisiciones', 0, 'manual'],
                ['606', 'Saldo crédito mes anterior por retenciones IVA', 0, 'manual'],
                ['609', 'Retenciones IVA que le hicieron este período', $c609, 'auto'],
                ['615', 'Saldo crédito próximo mes por adquisiciones', $c615, 'formula'],
                ['617', 'Saldo próximo mes por retenciones IVA', $c617, 'formula'],
                ['620', 'SUBTOTAL A PAGAR', $c620, 'formula'],
                ['621', 'IVA presuntivo combustibles / salas de juego', 0, 'manual'],
                ['699', 'TOTAL IMPUESTO A PAGAR POR PERCEPCIÓN', $c699, 'formula'],
            ]),
            $this->section('RETENCIONES IVA EFECTUADAS', [
                ['721', 'Retención IVA 10% efectuada', $ret[10] ?? 0.0, 'auto'],
                ['723', 'Retención IVA 20% efectuada', $ret[20] ?? 0.0, 'auto'],
                ['725', 'Retención IVA 30% efectuada', $ret[30] ?? 0.0, 'auto'],
                ['727', 'Retención IVA 50% efectuada', $ret[50] ?? 0.0, 'auto'],
                ['729', 'Retención IVA 70% efectuada', $ret[70] ?? 0.0, 'auto'],
                ['731', 'Retención IVA 100% efectuada', $ret[100] ?? 0.0, 'auto'],
                ['799', 'TOTAL IMPUESTO RETENIDO', $c799, 'formula'],
                ['801', 'TOTAL IMPUESTO A PAGAR POR RETENCIÓN', $c801, 'formula'],
            ]),
            $this->section('PAGO', [
                ['859', 'TOTAL CONSOLIDADO IVA', $c859, 'formula'],
                ['890', 'Pago previo (sustitutiva)', 0, 'manual'],
                ['902', 'TOTAL IMPUESTO A PAGAR', $c859, 'formula'],
                ['903', 'Interés por mora', 0, 'manual'],
                ['904', 'Multas', 0, 'manual'],
                ['905', 'Total a pagar', $c859, 'formula'],
                ['999', 'Total pagado', 0, 'manual'],
            ]),
        ];

        return ['sections' => $sections, 'unmapped' => []];
    }

    /**
     * Sumas de ventas: gravadas (12/15%), 5%, 0%, no objeto/exentas e IVA,
     * en bruto (solo documentos positivos) y NETO (bruto − notas de crédito).
     *
     * @return array<string, float>
     */
    private function ventas(int $companyId, string $startDate, string $endDate): array
    {
        $nc = "CASE WHEN vt.code = '04' THEN 1 ELSE 0 END";

        $row = DB::table('orders AS o')
            ->join('voucher_types AS vt', 'vt.id', '=', 'o.voucher_type_id')
            ->where('o.company_id', $companyId)
            ->where('o.state', 'AUTORIZADO')
            ->whereBetween('o.emision', [$startDate, $endDate])
            ->selectRaw("
                SUM(CASE WHEN {$nc} = 0 THEN o.base12 + o.base15 ELSE 0 END) AS gravada_bruto,
                SUM(CASE WHEN {$nc} = 0 THEN o.base5 ELSE 0 END) AS base5_bruto,
                SUM(CASE WHEN {$nc} = 0 THEN o.base0 ELSE 0 END) AS base0_bruto,
                SUM((o.base12 + o.base15) * (1 - 2 * {$nc})) AS gravada_neto,
                SUM(o.base5 * (1 - 2 * {$nc})) AS base5_neto,
                SUM(o.base0 * (1 - 2 * {$nc})) AS base0_neto,
                SUM((o.iva12 + o.iva15) * (1 - 2 * {$nc})) AS iva_neto,
                SUM(o.iva5 * (1 - 2 * {$nc})) AS iva5_neto,
                SUM((o.no_iva + o.exempt) * (1 - 2 * {$nc})) AS no_objeto_neto
            ")
            ->first();

        return array_map(fn ($x) => round((float) $x, 2), (array) $row);
    }

    /**
     * Sumas de compras segmentadas por sustento tributario:
     * con derecho a crédito (01/06), activo fijo con derecho (03),
     * sin derecho (resto), RIMPE Negocio Popular, tarifa 0/5%.
     * Sustento null se asume con derecho (01).
     *
     * @return array<string, float>
     */
    private function compras(int $companyId, string $startDate, string $endDate): array
    {
        $nc = "CASE WHEN vt.code = '04' THEN 1 ELSE 0 END";
        $credit = "(ts.code IN ('".implode("','", self::CREDIT_SUPPORTS)."') OR ts.code IS NULL)";
        $fixedAsset = "ts.code = '".self::FIXED_ASSET_CREDIT_SUPPORT."'";
        $rimpeNp = "ct.description = 'RIMPE NEGOCIO POPULAR'";
        $gravada = '(s.base8 + s.base12 + s.base15)';
        $iva = '(s.iva8 + s.iva12 + s.iva15)';

        $row = DB::table('shops AS s')
            ->join('voucher_types AS vt', 'vt.id', '=', 's.voucher_type_id')
            ->leftJoin('tax_supports AS ts', 'ts.id', '=', 's.tax_support_id')
            ->leftJoin('contacts AS c', 'c.id', '=', 's.contact_id')
            ->leftJoin('contributor_types AS ct', 'ct.id', '=', 'c.contributor_type_id')
            ->where('s.company_id', $companyId)
            ->where('s.state', 'AUTORIZADO')
            ->whereBetween('s.emision', [$startDate, $endDate])
            ->selectRaw("
                SUM(CASE WHEN {$nc} = 0 AND {$credit} AND NOT ({$rimpeNp}) THEN {$gravada} ELSE 0 END) AS credito_bruto,
                SUM(CASE WHEN {$nc} = 0 AND {$fixedAsset} THEN {$gravada} ELSE 0 END) AS af_bruto,
                SUM(CASE WHEN {$nc} = 0 AND NOT ({$credit}) AND NOT ({$fixedAsset}) THEN {$gravada} ELSE 0 END) AS sin_derecho_bruto,
                SUM(CASE WHEN {$nc} = 0 AND NOT ({$rimpeNp}) THEN s.base0 ELSE 0 END) AS base0_bruto,
                SUM(CASE WHEN {$nc} = 0 AND {$rimpeNp} THEN s.base0 + {$gravada} + s.base5 ELSE 0 END) AS rimpe_np_bruto,
                SUM(CASE WHEN {$nc} = 0 AND NOT ({$rimpeNp}) THEN s.base5 ELSE 0 END) AS base5_bruto,
                SUM(CASE WHEN {$credit} AND NOT ({$rimpeNp}) THEN {$gravada} * (1 - 2 * {$nc}) ELSE 0 END) AS credito_neto,
                SUM(CASE WHEN {$fixedAsset} THEN {$gravada} * (1 - 2 * {$nc}) ELSE 0 END) AS af_neto,
                SUM(CASE WHEN NOT ({$credit}) AND NOT ({$fixedAsset}) THEN {$gravada} * (1 - 2 * {$nc}) ELSE 0 END) AS sin_derecho_neto,
                SUM(CASE WHEN NOT ({$rimpeNp}) THEN s.base0 * (1 - 2 * {$nc}) ELSE 0 END) AS base0_neto,
                SUM(CASE WHEN {$rimpeNp} THEN (s.base0 + {$gravada} + s.base5) * (1 - 2 * {$nc}) ELSE 0 END) AS rimpe_np_neto,
                SUM(CASE WHEN NOT ({$rimpeNp}) THEN s.base5 * (1 - 2 * {$nc}) ELSE 0 END) AS base5_neto,
                SUM(CASE WHEN {$credit} AND NOT ({$rimpeNp}) THEN {$iva} * (1 - 2 * {$nc}) ELSE 0 END) AS iva_credito_neto,
                SUM(CASE WHEN {$fixedAsset} THEN {$iva} * (1 - 2 * {$nc}) ELSE 0 END) AS iva_af_neto,
                SUM(CASE WHEN NOT ({$credit}) AND NOT ({$fixedAsset}) THEN {$iva} * (1 - 2 * {$nc}) ELSE 0 END) AS iva_sin_derecho_neto,
                SUM(s.iva5 * (1 - 2 * {$nc})) AS iva5_neto,
                SUM(s.no_iva * (1 - 2 * {$nc})) AS no_objeto_neto,
                SUM(s.exempt * (1 - 2 * {$nc})) AS exentas_neto
            ")
            ->first();

        return array_map(fn ($x) => round((float) $x, 2), (array) $row);
    }

    /**
     * Retenciones de IVA: recibidas (sobre ventas, casillero 609) y
     * efectuadas (sobre compras, por porcentaje → 721-731).
     *
     * @return array{recibidas: float, efectuadas: array<int, float>}
     */
    private function retencionesIva(int $companyId, string $startDate, string $endDate): array
    {
        $recibidas = (float) DB::table('order_retention_items AS items')
            ->join('retentions AS r', 'r.id', '=', 'items.retention_id')
            ->join('orders AS o', 'o.id', '=', 'items.order_id')
            ->where('r.type', 'IVA')
            ->where('o.company_id', $companyId)
            ->where('o.state', 'AUTORIZADO')
            ->whereBetween('o.emision', [$startDate, $endDate])
            ->sum('items.value');

        $efectuadas = DB::table('shop_retention_items AS items')
            ->join('retentions AS r', 'r.id', '=', 'items.retention_id')
            ->join('shops AS s', 's.id', '=', 'items.shop_id')
            ->where('r.type', 'IVA')
            ->where('s.company_id', $companyId)
            ->where('s.state', 'AUTORIZADO')
            ->whereBetween('s.emision', [$startDate, $endDate])
            ->groupBy('items.percentage')
            ->selectRaw('items.percentage AS percentage, SUM(items.value) AS value')
            ->pluck('value', 'percentage')
            ->mapWithKeys(fn ($value, $pct) => [(int) $pct => round((float) $value, 2)])
            ->all();

        return ['recibidas' => round($recibidas, 2), 'efectuadas' => $efectuadas];
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: float|int|string|null, 3: string}>  $rows
     * @return array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}
     */
    private function section(string $name, array $rows): array
    {
        return [
            'section' => $name,
            'rows' => array_map(fn (array $r) => ['c' => $r[0], 'd' => $r[1], 'v' => $r[2], 't' => $r[3]], $rows),
        ];
    }
}
