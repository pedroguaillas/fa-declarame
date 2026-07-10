<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\F103DraftExport;
use App\Exports\F104DraftExport;
use App\Exports\SemesterReportExport;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderRetentionItem;
use App\Models\Tenant\Shop;
use App\Models\Tenant\ShopRetentionItem;
use App\Services\F103FormService;
use App\Services\F104FormService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeclarationController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2099'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:2'],
        ]);

        $company = company();
        $isSemiannual = $company->type_declaration === 'semestral';
        $companyId = (int) session('current_company_id');

        if ($isSemiannual) {
            [$year, $semester] = $this->resolvedSemester($request);
            [$startMonth, $endMonth] = $semester === 1 ? [1, 6] : [7, 12];
            $month = null;
        } else {
            [$year, $month] = $this->resolvedPeriod($request);
            [$startMonth, $endMonth] = [$month, $month];
            $semester = null;
        }

        return Inertia::render('Tenant/Declaration/Index', [
            'year' => $year,
            'month' => $month,
            'semester' => $semester,
            'typeDeclaration' => $company->type_declaration,
            'compras' => $this->comprasSummary($companyId, $year, $startMonth, $endMonth),
            'ventas' => $this->ventasSummary($companyId, $year, $startMonth, $endMonth),
            'f104' => Inertia::optional(fn () => app(F104FormService::class)->build($companyId, $year, $startMonth, $endMonth)),
            'f103' => Inertia::optional(fn () => app(F103FormService::class)->build($companyId, $year, $startMonth, $endMonth)),
        ]);
    }

    public function exportF103(Request $request): BinaryFileResponse
    {
        [$year, $startMonth, $endMonth, $periodLabel, $suffix] = $this->exportPeriod($request);
        $companyId = (int) session('current_company_id');

        $form = app(F103FormService::class)->build($companyId, $year, $startMonth, $endMonth);
        $sections = $this->applyOverrides($form['sections'], $request->input('values', []));

        $export = new F103DraftExport(
            sections: $sections,
            unmapped: $form['unmapped'],
            periodLabel: $periodLabel,
            ruc: company()?->ruc ?? '',
            companyName: company()?->name,
        );

        return Excel::download($export, "F103-Borrador-{$suffix}.xlsx");
    }

    public function exportF104(Request $request): BinaryFileResponse
    {
        [$year, $startMonth, $endMonth, $periodLabel, $suffix] = $this->exportPeriod($request);
        $companyId = (int) session('current_company_id');

        $form = app(F104FormService::class)->build($companyId, $year, $startMonth, $endMonth);
        $sections = $this->applyOverrides($form['sections'], $request->input('values', []));

        $export = new F104DraftExport(
            sections: $sections,
            unmapped: [],
            periodLabel: $periodLabel,
            ruc: company()?->ruc ?? '',
            companyName: company()?->name,
        );

        return Excel::download($export, "F104-Borrador-{$suffix}.xlsx");
    }

    /**
     * Sobrescribe valores enviados desde la página (manuales y fórmulas
     * recalculadas en el cliente) sobre las secciones calculadas.
     *
     * @param  array<int, array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}>  $sections
     * @param  array<string, mixed>  $overrides
     * @return array<int, array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}>
     */
    private function applyOverrides(array $sections, array $overrides): array
    {
        foreach ($sections as &$section) {
            foreach ($section['rows'] as &$row) {
                if (array_key_exists($row['c'], $overrides) && in_array($row['t'], ['manual', 'formula'], true)) {
                    $row['v'] = is_numeric($overrides[$row['c']]) ? round((float) $overrides[$row['c']], 2) : $overrides[$row['c']];
                }
            }
        }

        return $sections;
    }

    /**
     * Resuelve el período del export (mes o semestre) desde el request.
     *
     * @return array{int, int, int, string, string}
     */
    private function exportPeriod(Request $request): array
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'semester' => ['nullable', 'required_without:month', 'integer', 'min:1', 'max:2'],
            'month' => ['nullable', 'required_without:semester', 'integer', 'min:1', 'max:12'],
            'values' => ['nullable', 'array'],
        ]);

        $year = (int) $request->input('year');
        $ruc = company()?->ruc ?? '';

        if ($request->filled('semester')) {
            $semester = (int) $request->input('semester');
            [$startMonth, $endMonth] = $semester === 1 ? [1, 6] : [7, 12];
            $periodLabel = "SEMESTRE {$semester} {$year}";
            $suffix = "{$year}-S{$semester}-{$ruc}";
        } else {
            $month = (int) $request->input('month');
            [$startMonth, $endMonth] = [$month, $month];
            $monthName = mb_strtoupper(Carbon::create($year, $month)->locale('es')->monthName);
            $periodLabel = "{$monthName} {$year}";
            $suffix = sprintf('%s-%d-%s', $monthName, $year, $ruc);
        }

        return [$year, $startMonth, $endMonth, $periodLabel, $suffix];
    }

    public function exportSemester(Request $request): BinaryFileResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'semester' => ['nullable', 'required_without:month', 'integer', 'min:1', 'max:2'],
            'month' => ['nullable', 'required_without:semester', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) $request->input('year');

        if ($request->filled('semester')) {
            $semester = (int) $request->input('semester');
            [$startMonth, $endMonth] = $semester === 1 ? [1, 6] : [7, 12];
            $periodLabel = "Semestre {$semester} {$year}";
            $fileName = "reporte-declaracion-{$year}-S{$semester}.xlsx";
        } else {
            $month = (int) $request->input('month');
            [$startMonth, $endMonth] = [$month, $month];
            $periodLabel = ucfirst(Carbon::create($year, $month)->locale('es')->monthName)." {$year}";
            $fileName = sprintf('reporte-declaracion-%d-%02d.xlsx', $year, $month);
        }

        $startDate = sprintf('%d-%02d-01', $year, $startMonth);
        $endDate = Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d');
        $companyId = (int) session('current_company_id');

        $export = new SemesterReportExport(
            periodLabel: $periodLabel,
            resumen: [
                'compras' => $this->comprasSummary($companyId, $year, $startMonth, $endMonth),
                'ventas' => $this->ventasSummary($companyId, $year, $startMonth, $endMonth),
            ],
            compras: $this->comprasRows($companyId, $startDate, $endDate),
            ventas: $this->ventasRows($companyId, $startDate, $endDate),
            retencionesRecibidas: $this->retencionesRecibidasRows($companyId, $startDate, $endDate),
            retencionesEmitidas: $this->retencionesEmitidasRows($companyId, $startDate, $endDate),
            logoPath: currentTenant()?->logo_path,
            companyName: company()?->name,
        );

        return Excel::download($export, $fileName);
    }

    /** @return array<int, array<string, mixed>> */
    private function comprasRows(int $companyId, string $startDate, string $endDate): array
    {
        return Shop::query()
            ->where('company_id', $companyId)
            ->where('state', 'AUTORIZADO')
            ->whereBetween('emision', [$startDate, $endDate])
            ->with(['contact:id,identification,name', 'voucherType:id,code,description'])
            ->orderBy('emision')
            ->get(['id', 'contact_id', 'voucher_type_id', 'emision', 'serie', 'sub_total', 'no_iva', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'total'])
            ->map(function (Shop $shop) {
                $sign = $this->voucherSign($shop->voucherType?->code);

                return [
                    'emision' => $shop->emision?->format('d-m-Y') ?? '',
                    'voucher_type' => $shop->voucherType?->description ?? '',
                    'serie' => $shop->serie ?? '',
                    'identification' => $shop->contact?->identification ?? '',
                    'name' => $shop->contact?->name ?? '',
                    'sub_total' => $sign * (float) $shop->sub_total,
                    'no_iva' => $sign * (float) $shop->no_iva,
                    'base0' => $sign * (float) $shop->base0,
                    'base5' => $sign * (float) $shop->base5,
                    'base8' => $sign * (float) $shop->base8,
                    'base12' => $sign * (float) $shop->base12,
                    'base15' => $sign * (float) $shop->base15,
                    'iva5' => $sign * (float) $shop->iva5,
                    'iva8' => $sign * (float) $shop->iva8,
                    'iva12' => $sign * (float) $shop->iva12,
                    'iva15' => $sign * (float) $shop->iva15,
                    'total' => $sign * (float) $shop->total,
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function ventasRows(int $companyId, string $startDate, string $endDate): array
    {
        return Order::query()
            ->where('company_id', $companyId)
            ->whereBetween('emision', [$startDate, $endDate])
            ->with(['contact:id,identification,name', 'voucherType:id,code,description'])
            ->orderBy('emision')
            ->get(['id', 'contact_id', 'voucher_type_id', 'emision', 'serie', 'sub_total', 'no_iva', 'base0', 'base5', 'base12', 'base15', 'iva5', 'iva12', 'iva15', 'total'])
            ->map(function (Order $order) {
                $sign = $this->voucherSign($order->voucherType?->code);

                return [
                    'emision' => $order->emision?->format('d-m-Y') ?? '',
                    'voucher_type' => $order->voucherType?->description ?? '',
                    'serie' => $order->serie ?? '',
                    'identification' => $order->contact?->identification ?? '',
                    'name' => $order->contact?->name ?? '',
                    'sub_total' => $sign * (float) $order->sub_total,
                    'no_iva' => $sign * (float) $order->no_iva,
                    'base0' => $sign * (float) $order->base0,
                    'base5' => $sign * (float) $order->base5,
                    'base12' => $sign * (float) $order->base12,
                    'base15' => $sign * (float) $order->base15,
                    'iva5' => $sign * (float) $order->iva5,
                    'iva12' => $sign * (float) $order->iva12,
                    'iva15' => $sign * (float) $order->iva15,
                    'total' => $sign * (float) $order->total,
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function retencionesRecibidasRows(int $companyId, string $startDate, string $endDate): array
    {
        return OrderRetentionItem::query()
            ->with([
                'retention:id,code,description,type',
                'order:id,contact_id,serie,serie_retention,date_retention',
                'order.contact:id,identification,name',
            ])
            ->whereHas('order', function ($q) use ($companyId, $startDate, $endDate) {
                $q->where('company_id', $companyId)->whereBetween('emision', [$startDate, $endDate]);
            })
            ->orderBy('order_id')
            ->get(['id', 'order_id', 'retention_id', 'base', 'percentage', 'value'])
            ->map(fn (OrderRetentionItem $item) => [
                'date_retention' => $item->order?->date_retention?->format('d-m-Y') ?? '',
                'serie_retention' => $item->order?->serie_retention ?? '',
                'voucher' => $item->order?->serie ?? '',
                'identification' => $item->order?->contact?->identification ?? '',
                'name' => $item->order?->contact?->name ?? '',
                'code' => $item->retention?->code ?? '',
                'type' => $item->retention?->type ?? '',
                'description' => $item->retention?->description ?? '',
                'base' => (float) $item->base,
                'percentage' => (float) $item->percentage,
                'value' => (float) $item->value,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function retencionesEmitidasRows(int $companyId, string $startDate, string $endDate): array
    {
        return ShopRetentionItem::query()
            ->with([
                'retention:id,code,description,type',
                'shop:id,contact_id,serie,serie_retention,date_retention',
                'shop.contact:id,identification,name',
            ])
            ->whereHas('shop', function ($q) use ($companyId, $startDate, $endDate) {
                $q->where('company_id', $companyId)->whereBetween('emision', [$startDate, $endDate]);
            })
            ->orderBy('shop_id')
            ->get(['id', 'shop_id', 'retention_id', 'base', 'percentage', 'value'])
            ->map(fn (ShopRetentionItem $item) => [
                'date_retention' => $item->shop?->date_retention?->format('d-m-Y') ?? '',
                'serie_retention' => $item->shop?->serie_retention ?? '',
                'voucher' => $item->shop?->serie ?? '',
                'identification' => $item->shop?->contact?->identification ?? '',
                'name' => $item->shop?->contact?->name ?? '',
                'code' => $item->retention?->code ?? '',
                'type' => $item->retention?->type ?? '',
                'description' => $item->retention?->description ?? '',
                'base' => (float) $item->base,
                'percentage' => (float) $item->percentage,
                'value' => (float) $item->value,
            ])
            ->all();
    }

    /** @return array{count: int, subtotal: float, iva: float, total: float, retentions: float, a_pagar: float} */
    private function comprasSummary(int $companyId, int $year, int $startMonth, int $endMonth): array
    {
        $startDate = sprintf('%d-%02d-01', $year, $startMonth);
        $endDate = Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d');
        $sign = $this->signSql();

        $row = Shop::query()
            ->join('voucher_types AS vt', 'vt.id', 'shops.voucher_type_id')
            ->leftJoinSub($this->retentionTotalsSub('shop_retention_items', 'shop_id'), 'ret', 'ret.shop_id', 'shops.id')
            ->where('shops.company_id', $companyId)
            ->where('shops.state', 'AUTORIZADO')
            ->whereBetween('shops.emision', [$startDate, $endDate])
            ->selectRaw("
                COUNT(*) AS doc_count,
                SUM({$sign} * shops.sub_total) AS subtotal,
                SUM({$sign} * (shops.iva5 + shops.iva8 + shops.iva12 + shops.iva15)) AS iva,
                SUM({$sign} * shops.total) AS total,
                SUM(COALESCE(ret.total_retention, 0)) AS retentions
            ")
            ->first();

        $total = (float) $row->total;
        $retentions = (float) $row->retentions;

        return [
            'count' => (int) $row->doc_count,
            'subtotal' => round((float) $row->subtotal, 2),
            'iva' => round((float) $row->iva, 2),
            'total' => round($total, 2),
            'retentions' => round($retentions, 2),
            'a_pagar' => round($total - $retentions, 2),
        ];
    }

    /** @return array{count: int, subtotal: float, iva: float, total: float, retentions: float, a_cobrar: float} */
    private function ventasSummary(int $companyId, int $year, int $startMonth, int $endMonth): array
    {
        $startDate = sprintf('%d-%02d-01', $year, $startMonth);
        $endDate = Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d');
        $sign = $this->signSql();

        $row = Order::query()
            ->join('voucher_types AS vt', 'vt.id', 'orders.voucher_type_id')
            ->leftJoinSub($this->retentionTotalsSub('order_retention_items', 'order_id'), 'ret', 'ret.order_id', 'orders.id')
            ->where('orders.company_id', $companyId)
            ->whereBetween('orders.emision', [$startDate, $endDate])
            ->selectRaw("
                COUNT(*) AS doc_count,
                SUM({$sign} * orders.sub_total) AS subtotal,
                SUM({$sign} * (orders.iva5 + orders.iva12 + orders.iva15)) AS iva,
                SUM({$sign} * orders.total) AS total,
                SUM(COALESCE(ret.total_retention, 0)) AS retentions
            ")
            ->first();

        $total = (float) $row->total;
        $retentions = (float) $row->retentions;

        return [
            'count' => (int) $row->doc_count,
            'subtotal' => round((float) $row->subtotal, 2),
            'iva' => round((float) $row->iva, 2),
            'total' => round($total, 2),
            'retentions' => round($retentions, 2),
            'a_cobrar' => round($total - $retentions, 2),
        ];
    }

    /** Expresión de signo: nota de crédito (04) resta. */
    private function signSql(): string
    {
        return "(CASE WHEN vt.code = '04' THEN -1 ELSE 1 END)";
    }

    /** Totales de retención por documento, agregables en el GROUP BY externo. */
    private function retentionTotalsSub(string $table, string $foreignKey): QueryBuilder
    {
        return DB::table($table)
            ->selectRaw("{$foreignKey}, SUM(value) AS total_retention")
            ->groupBy($foreignKey);
    }

    /** Nota de crédito (04) resta: sus valores van en negativo. */
    private function voucherSign(?string $voucherTypeCode): int
    {
        return $voucherTypeCode === '04' ? -1 : 1;
    }

    /** @return array{int, int} */
    private function resolvedPeriod(Request $request): array
    {
        if ($request->filled('year') && $request->filled('month')) {
            return [(int) $request->input('year'), (int) $request->input('month')];
        }

        $previousMonth = now('America/Guayaquil')->subMonth();

        return [$previousMonth->year, $previousMonth->month];
    }

    /** @return array{int, int} [year, semester] */
    private function resolvedSemester(Request $request): array
    {
        if ($request->filled('year') && $request->filled('semester')) {
            return [(int) $request->input('year'), (int) $request->input('semester')];
        }

        $now = now('America/Guayaquil');

        // Return previous semester
        if ($now->month <= 6) {
            return [$now->year - 1, 2];
        }

        return [$now->year, 1];
    }
}
