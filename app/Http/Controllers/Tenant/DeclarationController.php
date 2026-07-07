<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\SemesterReportExport;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderRetentionItem;
use App\Models\Tenant\Shop;
use App\Models\Tenant\ShopRetentionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

            return Inertia::render('Tenant/Declaration/Index', [
                'year' => $year,
                'month' => null,
                'semester' => $semester,
                'typeDeclaration' => $company->type_declaration,
                'compras' => $this->comprasSummary($companyId, $year, $startMonth, $endMonth),
                'ventas' => $this->ventasSummary($companyId, $year, $startMonth, $endMonth),
            ]);
        }

        [$year, $month] = $this->resolvedPeriod($request);

        return Inertia::render('Tenant/Declaration/Index', [
            'year' => $year,
            'month' => $month,
            'semester' => null,
            'typeDeclaration' => $company->type_declaration,
            'compras' => $this->comprasSummary($companyId, $year, $month, $month),
            'ventas' => $this->ventasSummary($companyId, $year, $month, $month),
        ]);
    }

    public function exportSemester(Request $request): BinaryFileResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'semester' => ['required', 'integer', 'min:1', 'max:2'],
        ]);

        $year = (int) $request->input('year');
        $semester = (int) $request->input('semester');
        [$startMonth, $endMonth] = $semester === 1 ? [1, 6] : [7, 12];
        $startDate = sprintf('%d-%02d-01', $year, $startMonth);
        $endDate = Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d');
        $companyId = (int) session('current_company_id');

        $export = new SemesterReportExport(
            year: $year,
            semester: $semester,
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

        return Excel::download($export, "reporte-semestral-{$year}-S{$semester}.xlsx");
    }

    /** @return array<int, array<string, mixed>> */
    private function comprasRows(int $companyId, string $startDate, string $endDate): array
    {
        return Shop::query()
            ->where('company_id', $companyId)
            ->where('state', 'AUTORIZADO')
            ->whereBetween('emision', [$startDate, $endDate])
            ->with(['contact:id,identification,name', 'voucherType:id,description'])
            ->orderBy('emision')
            ->get()
            ->map(fn (Shop $shop) => [
                'emision' => $shop->emision?->format('d-m-Y') ?? '',
                'voucher_type' => $shop->voucherType?->description ?? '',
                'serie' => $shop->serie ?? '',
                'identification' => $shop->contact?->identification ?? '',
                'name' => $shop->contact?->name ?? '',
                'sub_total' => $shop->sub_total,
                'no_iva' => $shop->no_iva,
                'base0' => $shop->base0,
                'base5' => $shop->base5,
                'base8' => $shop->base8,
                'base12' => $shop->base12,
                'base15' => $shop->base15,
                'iva5' => $shop->iva5,
                'iva8' => $shop->iva8,
                'iva12' => $shop->iva12,
                'iva15' => $shop->iva15,
                'total' => $shop->total,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function ventasRows(int $companyId, string $startDate, string $endDate): array
    {
        return Order::query()
            ->where('company_id', $companyId)
            ->whereBetween('emision', [$startDate, $endDate])
            ->with(['contact:id,identification,name', 'voucherType:id,description'])
            ->orderBy('emision')
            ->get()
            ->map(fn (Order $order) => [
                'emision' => $order->emision?->format('d-m-Y') ?? '',
                'voucher_type' => $order->voucherType?->description ?? '',
                'serie' => $order->serie ?? '',
                'identification' => $order->contact?->identification ?? '',
                'name' => $order->contact?->name ?? '',
                'sub_total' => $order->sub_total,
                'no_iva' => $order->no_iva,
                'base0' => $order->base0,
                'base5' => $order->base5,
                'base12' => $order->base12,
                'base15' => $order->base15,
                'iva5' => $order->iva5,
                'iva12' => $order->iva12,
                'iva15' => $order->iva15,
                'total' => $order->total,
            ])
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
            ->get()
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
            ->get()
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

        $shops = Shop::query()
            ->where('company_id', $companyId)
            ->where('state', 'AUTORIZADO')
            ->whereBetween('emision', [$startDate, $endDate])
            ->withSum('retentionItems as total_retention', 'value')
            ->get(['id', 'sub_total', 'iva5', 'iva8', 'iva12', 'iva15', 'total']);

        $subtotal = $shops->sum(fn ($s) => (float) $s->sub_total);
        $iva = $shops->sum(fn ($s) => (float) $s->iva5 + (float) $s->iva8 + (float) $s->iva12 + (float) $s->iva15);
        $total = $shops->sum(fn ($s) => (float) $s->total);
        $retentions = $shops->sum(fn ($s) => (float) $s->total_retention);

        return [
            'count' => $shops->count(),
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
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

        $orders = Order::query()
            ->where('company_id', $companyId)
            ->whereBetween('emision', [$startDate, $endDate])
            ->withSum('retentionItems as total_retention', 'value')
            ->get(['id', 'sub_total', 'iva5', 'iva12', 'iva15', 'total']);

        $subtotal = $orders->sum(fn ($o) => (float) $o->sub_total);
        $iva = $orders->sum(fn ($o) => (float) $o->iva5 + (float) $o->iva12 + (float) $o->iva15);
        $total = $orders->sum(fn ($o) => (float) $o->total);
        $retentions = $orders->sum(fn ($o) => (float) $o->total_retention);

        return [
            'count' => $orders->count(),
            'subtotal' => round($subtotal, 2),
            'iva' => round($iva, 2),
            'total' => round($total, 2),
            'retentions' => round($retentions, 2),
            'a_cobrar' => round($total - $retentions, 2),
        ];
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
