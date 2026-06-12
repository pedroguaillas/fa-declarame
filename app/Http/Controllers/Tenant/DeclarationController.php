<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shop;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    /** @return array{count: int, subtotal: float, iva: float, total: float, retentions: float, a_pagar: float} */
    private function comprasSummary(int $companyId, int $year, int $startMonth, int $endMonth): array
    {
        $startDate = sprintf('%d-%02d-01', $year, $startMonth);
        $endDate = Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d');

        $shops = Shop::query()
            ->where('company_id', $companyId)
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
