<?php

namespace App\Services;

use App\Models\Tenant\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calcula los casilleros del Formulario 103 (Retenciones en la Fuente de
 * Impuesto a la Renta) a partir de las retenciones emitidas en compras
 * (shop_retention_items tipo RENTA) del período.
 */
class F103FormService
{
    /**
     * Código AIR de retención → [casillero base, casillero retenido].
     * Códigos 5xx (pagos a no residentes) no se mapean: van a la lista
     * de no mapeados para revisión manual (sección exterior del F103).
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const CASILLERO_MAP = [
        '302' => ['302', '352'],
        '303' => ['303', '353'],
        '303A' => ['3030', '3530'],
        '304' => ['304', '354'],
        '304A' => ['304', '354'],
        '304B' => ['304', '354'],
        '304C' => ['304', '354'],
        '304D' => ['304', '354'],
        '304E' => ['304', '354'],
        '307' => ['307', '357'],
        '308' => ['308', '358'],
        '309' => ['309', '359'],
        '310' => ['310', '360'],
        '311' => ['311', '361'],
        '312' => ['312', '362'],
        '312A' => ['3120', '3620'],
        '312C' => ['3121', '3621'],
        '314A' => ['314', '364'],
        '314B' => ['314', '364'],
        '314C' => ['314', '364'],
        '314D' => ['314', '364'],
        '319' => ['319', '369'],
        '320' => ['320', '370'],
        '322' => ['322', '372'],
        '323' => ['323', '373'],
        '323A' => ['323', '373'],
        '323B1' => ['323', '373'],
        '323E' => ['323', '373'],
        '323E2' => ['3230', null],
        '323F' => ['323', '373'],
        '323G' => ['323', '373'],
        '323H' => ['323', '373'],
        '323I' => ['323', '373'],
        '323 M' => ['323', '373'],
        '323 N' => ['3230', null],
        '323 O' => ['3230', null],
        '323 P' => ['323', '373'],
        '323Q' => ['323', '373'],
        '323R' => ['3230', null],
        '323S' => ['323', '373'],
        '323T' => ['3230', null],
        '323U' => ['3230', null],
        '324A' => ['324', '374'],
        '324B' => ['324', '374'],
        '324C' => ['324', '374'],
        '325' => ['325', '375'],
        '325A' => ['325', '375'],
        '326' => ['326', '376'],
        '327' => ['327', '377'],
        '328' => ['328', '378'],
        // Seeder 331 = dividendos en acciones (capitalización) → casillero 329 del F103
        '331' => ['329', '379'],
        // Seeder 329 = fideicomisos residentes → casillero 330 (otros)
        '329' => ['330', '380'],
        '332' => ['332', null],
        '332B' => ['332', null],
        '332C' => ['332', null],
        '332D' => ['332', null],
        '332E' => ['332', null],
        '332F' => ['332', null],
        '332G' => ['332', null],
        '332H' => ['332', null],
        '332I' => ['332', null],
        '333' => ['333', '383'],
        '334' => ['334', '384'],
        '335' => ['335', '385'],
        '336' => ['336', '386'],
        '337' => ['337', '387'],
        '338' => ['3380', '3880'],
        '340' => ['3400', '3900'],
        '343' => ['343', '393'],
        '343A' => ['344', '394'],
        '343B' => ['3430', '3450'],
        '343C' => ['344', '394'],
        '3440' => ['3440', '3940'],
        '344A' => ['344', '394'],
        '344B' => ['344', '394'],
        '345' => ['346', '396'],
        '346' => ['346', '396'],
        '346A' => ['346', '396'],
        '346B' => ['346', '396'],
        '346D' => ['3370', '3870'],
        '350' => ['350', '400'],
        '351' => ['346', '396'],
        '3480' => ['3480', '3980'],
        '3482' => ['3140', '3640'],
    ];

    /** Casilleros base de operaciones país que suman al 349. */
    private const BASES_PAIS = [
        '302', '303', '3030', '304', '307', '308', '309', '310', '311',
        '312', '322', '3120', '3121', '3430', '343', '344', '332',
        '314', '3140', '319', '320',
        '323', '324', '3230', '325', '326', '327', '328', '329', '330',
        '333', '334', '335',
        '3481', '336', '337', '3370', '350', '3440', '346',
        '3400', '3380', '3480',
    ];

    /** Casilleros retenido de operaciones país que suman al 399. */
    private const RETENIDOS_PAIS = [
        '352', '353', '3530', '354', '357', '358', '359', '360', '361',
        '362', '372', '3620', '3621', '3450', '393', '394',
        '364', '3640', '369', '370',
        '373', '374', '375', '376', '377', '378', '379', '380',
        '383', '384', '385',
        '3981', '386', '387', '3870', '400', '3940', '396',
        '3900', '3880', '3980',
    ];

    /**
     * @return array{sections: array<int, array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}>, unmapped: array<int, array{code: string, base: float, value: float}>}
     */
    public function build(int $companyId, int $year, int $startMonth, int $endMonth): array
    {
        [$values, $unmapped] = $this->aggregateRetentions($companyId, $year, $startMonth, $endMonth);

        $company = Company::withoutGlobalScopes()->find($companyId, ['id', 'ruc', 'name']);

        $v = fn (string $c): float => round($values[$c] ?? 0, 2);

        $sections = [
            $this->section('IDENTIFICACIÓN', [
                // Como string para mostrarse sin formato decimal en página y Excel
                ['101', 'Mes', (string) $startMonth, 'auto'],
                ['102', 'Año', (string) $year, 'auto'],
                ['104', 'Nº form. que sustituye', null, 'manual'],
                ['105', 'Nº autorización form. anterior', null, 'manual'],
                ['198', 'RUC', $company?->ruc, 'auto'],
                ['199', 'Razón social', $company?->name, 'auto'],
            ]),
            $this->section('RELACIÓN DE DEPENDENCIA', [
                ['302', 'En relación de dependencia que supera o no la base desgravada — Base', $v('302'), 'manual'],
                ['352', 'En relación de dependencia — Retenido', $v('352'), 'manual'],
            ]),
            $this->section('SERVICIOS PRESTADOS', [
                ['303', 'Honorarios profesionales — Base', $v('303'), 'auto'],
                ['353', 'Honorarios profesionales — Retenido', $v('353'), 'auto'],
                ['3030', 'Servicios profesionales prestados por sociedades — Base', $v('3030'), 'auto'],
                ['3530', 'Servicios profesionales por sociedades — Retenido', $v('3530'), 'auto'],
                ['304', 'Servicios predomina el intelecto — Base', $v('304'), 'auto'],
                ['354', 'Servicios predomina el intelecto — Retenido', $v('354'), 'auto'],
                ['307', 'Servicios predomina la mano de obra — Base', $v('307'), 'auto'],
                ['357', 'Servicios predomina la mano de obra — Retenido', $v('357'), 'auto'],
                ['308', 'Utilización o aprovechamiento de imagen o renombre — Base', $v('308'), 'auto'],
                ['358', 'Imagen o renombre — Retenido', $v('358'), 'auto'],
                ['309', 'Servicios publicidad y comunicación — Base', $v('309'), 'auto'],
                ['359', 'Servicios publicidad y comunicación — Retenido', $v('359'), 'auto'],
                ['310', 'Transporte privado de pasajeros o público/privado de carga — Base', $v('310'), 'auto'],
                ['360', 'Transporte — Retenido', $v('360'), 'auto'],
                ['311', 'Liquidaciones de compra (nivel cultural o rusticidad) — Base', $v('311'), 'auto'],
                ['361', 'Liquidaciones de compra — Retenido', $v('361'), 'auto'],
            ]),
            $this->section('BIENES Y SERVICIOS', [
                ['312', 'Transferencia de bienes muebles de naturaleza corporal — Base', $v('312'), 'auto'],
                ['362', 'Transferencia de bienes muebles — Retenido', $v('362'), 'auto'],
                ['322', 'Seguros y reaseguros (primas y cesiones) — Base', $v('322'), 'auto'],
                ['372', 'Seguros y reaseguros — Retenido', $v('372'), 'auto'],
                ['3120', 'Compras al productor (agrícola/avícola/pecuario/forestal) — Base', $v('3120'), 'auto'],
                ['3620', 'Compras al productor — Retenido', $v('3620'), 'auto'],
                ['3121', 'Compras al comercializador — Base', $v('3121'), 'auto'],
                ['3621', 'Compras al comercializador — Retenido', $v('3621'), 'auto'],
                ['3430', 'Actividades de construcción de obra material inmueble — Base', $v('3430'), 'auto'],
                ['3450', 'Actividades de construcción — Retenido', $v('3450'), 'auto'],
                ['343', 'Pagos aplicables al 1% (RIMPE Emprendedores) — Base', $v('343'), 'auto'],
                ['393', 'Pagos aplicables al 1% — Retenido', $v('393'), 'auto'],
                ['344', 'Pagos aplicables al 2% (energía, tarjetas, minerales) — Base', $v('344'), 'auto'],
                ['394', 'Pagos aplicables al 2% — Retenido', $v('394'), 'auto'],
                ['332', 'Pagos de bienes y servicios no sujetos a retención o 0% — Base', $v('332'), 'auto'],
            ]),
            $this->section('REGALÍAS Y ARRENDAMIENTOS', [
                ['314', 'Regalías, derechos de autor, marcas, patentes — Base', $v('314'), 'auto'],
                ['364', 'Regalías, derechos de autor — Retenido', $v('364'), 'auto'],
                ['3140', 'Comisiones pagadas a sociedades residentes — Base', $v('3140'), 'auto'],
                ['3640', 'Comisiones pagadas a sociedades — Retenido', $v('3640'), 'auto'],
                ['319', 'Arrendamiento mercantil — Base', $v('319'), 'auto'],
                ['369', 'Arrendamiento mercantil — Retenido', $v('369'), 'auto'],
                ['320', 'Arrendamiento de bienes inmuebles — Base', $v('320'), 'auto'],
                ['370', 'Arrendamiento de bienes inmuebles — Retenido', $v('370'), 'auto'],
            ]),
            $this->section('CAPITAL', [
                ['323', 'Rendimientos financieros — Base', $v('323'), 'auto'],
                ['373', 'Rendimientos financieros — Retenido', $v('373'), 'auto'],
                ['324', 'Rendimientos financieros entre IFI y EPS — Base', $v('324'), 'auto'],
                ['374', 'Rendimientos entre IFI y EPS — Retenido', $v('374'), 'auto'],
                ['3230', 'Otros rendimientos financieros gravados 0% — Base', $v('3230'), 'auto'],
                ['325', 'Dividendos (anticipos y préstamos accionistas) — Base', $v('325'), 'auto'],
                ['375', 'Dividendos — Retenido', $v('375'), 'auto'],
                ['326', 'Dividendos impuesto renta único art. 27 LRTI — Base', $v('326'), 'auto'],
                ['376', 'Dividendos único — Retenido', $v('376'), 'auto'],
                ['327', 'Dividendos a personas naturales residentes — Base', $v('327'), 'auto'],
                ['377', 'Dividendos PN residentes — Retenido', $v('377'), 'auto'],
                ['328', 'Dividendos a sociedades residentes — Base', $v('328'), 'auto'],
                ['378', 'Dividendos a sociedades residentes — Retenido', $v('378'), 'auto'],
                ['329', 'Dividendos en acciones (capitalización de utilidades) — Base', $v('329'), 'auto'],
                ['379', 'Dividendos en acciones — Retenido', $v('379'), 'auto'],
                ['330', 'Dividendos distribuidos — otros — Base', $v('330'), 'auto'],
                ['380', 'Dividendos — otros — Retenido', $v('380'), 'auto'],
                ['333', 'Ganancia enajenación derechos representativos cotizados — Base', $v('333'), 'auto'],
                ['383', 'Ganancia enajenación cotizados — Retenido', $v('383'), 'auto'],
                ['334', 'Contraprestación enajenación derechos no cotizados — Base', $v('334'), 'auto'],
                ['384', 'Contraprestación no cotizados — Retenido', $v('384'), 'auto'],
            ]),
            $this->section('LOTERÍAS Y PREMIOS', [
                ['335', 'Loterías, rifas, apuestas y similares (15%) — Base', $v('335'), 'auto'],
                ['385', 'Loterías, rifas, apuestas — Retenido', $v('385'), 'auto'],
            ]),
            $this->section('AUTORRETENCIONES Y OTRAS', [
                ['3481', 'Autorretenciones Sociedades Grandes Contribuyentes — Base', $v('3481'), 'manual'],
                ['3981', 'Autorretenciones Grandes Contribuyentes — Retenido', $v('3981'), 'manual'],
                ['336', 'Venta de combustibles a comercializadoras — Base', $v('336'), 'auto'],
                ['386', 'Venta de combustibles a comercializadoras — Retenido', $v('386'), 'auto'],
                ['337', 'Venta de combustibles a distribuidores — Base', $v('337'), 'auto'],
                ['387', 'Venta de combustibles a distribuidores — Retenido', $v('387'), 'auto'],
                ['3370', 'Retención a cargo del propio sujeto pasivo — productos forestales — Base', $v('3370'), 'auto'],
                ['3870', 'Comercialización productos forestales — Retenido', $v('3870'), 'auto'],
                ['350', 'Otras autorretenciones (Art.92.1 RLRTI) — Base', $v('350'), 'manual'],
                ['400', 'Otras autorretenciones — Retenido', $v('400'), 'manual'],
                ['3440', 'Otras retenciones aplicables al 3% — Base', $v('3440'), 'auto'],
                ['3940', 'Otras retenciones al 3% — Retenido', $v('3940'), 'auto'],
                ['346', 'Otras retenciones aplicables a otros porcentajes — Base', $v('346'), 'auto'],
                ['396', 'Otras retenciones a otros porcentajes — Retenido', $v('396'), 'auto'],
            ]),
            $this->section('BANANO', [
                ['3400', 'Impuesto único a la exportación de banano — Base', $v('3400'), 'manual'],
                ['3900', 'Impuesto único exportación banano — Retenido', $v('3900'), 'manual'],
                ['3380', 'Producción y venta local de banano — Base', $v('3380'), 'manual'],
                ['3880', 'Producción y venta local de banano — Retenido', $v('3880'), 'manual'],
            ]),
            $this->section('PRONÓSTICOS DEPORTIVOS', [
                ['3480', 'Impuesto renta único pronósticos deportivos — Base', $v('3480'), 'manual'],
                ['3980', 'Impuesto único pronósticos deportivos — Retenido', $v('3980'), 'manual'],
            ]),
            $this->section('PAGOS AL EXTERIOR (NO RESIDENTES)', [
                ['402', 'Intereses por financiamiento de proveedores externos — Base', 0, 'manual'],
                ['424', 'Intereses créditos externos — Retenido', 0, 'manual'],
                ['410', 'Servicios técnicos, administrativos, consultoría — Base', 0, 'manual'],
                ['431', 'Servicios técnicos — Retenido', 0, 'manual'],
                ['411', 'Otros conceptos exterior — Base', 0, 'manual'],
                ['432', 'Otros conceptos — Retenido', 0, 'manual'],
                ['412', 'Otros conceptos no sujetos exterior — Base', 0, 'manual'],
                ['433', 'Otros no sujetos — Retenido', 0, 'manual'],
            ]),
        ];

        $sections[] = $this->totalsSection($sections);

        return ['sections' => $sections, 'unmapped' => $unmapped];
    }

    /**
     * Fórmulas del formulario calculadas desde las filas ya construidas.
     *
     * @param  array<int, array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}>  $sections
     * @return array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}
     */
    private function totalsSection(array $sections): array
    {
        $flat = [];

        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                $flat[$row['c']] = is_numeric($row['v']) ? (float) $row['v'] : 0.0;
            }
        }

        $sum = fn (array $casilleros): float => round(array_sum(array_map(fn ($c) => $flat[$c] ?? 0.0, $casilleros)), 2);

        $c349 = $sum(self::BASES_PAIS);
        $c399 = $sum(self::RETENIDOS_PAIS);
        $c498 = $sum(['402', '410', '411', '412']);
        $c499 = round($c399 + $sum(['424', '431', '432', '433']), 2);

        return $this->section('TOTALES Y PAGO', [
            ['349', 'SUBTOTAL operaciones efectuadas en el país — bases', $c349, 'formula'],
            ['399', 'SUBTOTAL operaciones efectuadas en el país — retenido', $c399, 'formula'],
            ['498', 'Subtotal operaciones efectuadas con el exterior — bases', $c498, 'formula'],
            ['499', 'TOTAL de retención de impuesto a la renta', $c499, 'formula'],
            ['500', 'Compensación de pago a cuenta por utilidades no distribuidas', 0, 'manual'],
            ['501', 'TOTAL de retención IR después de compensación', $c499, 'formula'],
            ['902', 'TOTAL IMPUESTO A PAGAR', $c499, 'formula'],
            ['903', 'Interés por mora', 0, 'manual'],
            ['904', 'Multas', 0, 'manual'],
            ['905', 'Total a pagar', $c499, 'formula'],
            ['999', 'Total pagado', 0, 'manual'],
        ]);
    }

    /**
     * Suma base y valor de retenciones RENTA del período, distribuidas a casilleros.
     *
     * @return array{0: array<string, float>, 1: array<int, array{code: string, base: float, value: float}>}
     */
    private function aggregateRetentions(int $companyId, int $year, int $startMonth, int $endMonth): array
    {
        $startDate = sprintf('%d-%02d-01', $year, $startMonth);
        $endDate = Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d');

        $rows = DB::table('shop_retention_items AS items')
            ->join('retentions AS r', 'r.id', '=', 'items.retention_id')
            ->join('shops AS s', 's.id', '=', 'items.shop_id')
            ->where('r.type', 'RENTA')
            ->where('s.company_id', $companyId)
            ->where('s.state', 'AUTORIZADO')
            ->whereBetween('s.emision', [$startDate, $endDate])
            ->groupBy('r.code')
            ->selectRaw('r.code AS code, SUM(items.base) AS base, SUM(items.value) AS value')
            ->get();

        $values = [];
        $unmapped = [];

        foreach ($rows as $row) {
            $mapping = self::CASILLERO_MAP[$row->code] ?? null;

            if ($mapping === null) {
                $unmapped[] = [
                    'code' => $row->code,
                    'base' => round((float) $row->base, 2),
                    'value' => round((float) $row->value, 2),
                ];

                continue;
            }

            [$baseCasillero, $retenidoCasillero] = $mapping;
            $values[$baseCasillero] = ($values[$baseCasillero] ?? 0) + (float) $row->base;

            if ($retenidoCasillero !== null) {
                $values[$retenidoCasillero] = ($values[$retenidoCasillero] ?? 0) + (float) $row->value;
            }
        }

        return [$values, $unmapped];
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
