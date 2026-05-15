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
        ]);

        [$year, $month] = $this->resolvedPeriod($request);

        $companyId = (int) session('current_company_id');

        return Inertia::render('Tenant/Declaration/Index', [
            'year' => $year,
            'month' => $month,
            'compras' => $this->comprasSummary($companyId, $year, $month),
            'ventas' => $this->ventasSummary($companyId, $year, $month),
        ]);
    }

    /** @return array{count: int, subtotal: float, iva: float, total: float, retentions: float, a_pagar: float} */
    private function comprasSummary(int $companyId, int $year, int $month): array
    {
        $shops = Shop::query()
            ->where('company_id', $companyId)
            ->whereYear('emision', $year)
            ->whereMonth('emision', $month)
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
    private function ventasSummary(int $companyId, int $year, int $month): array
    {
        $orders = Order::query()
            ->where('company_id', $companyId)
            ->whereYear('emision', $year)
            ->whereMonth('emision', $month)
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

        $lastShop = Shop::max('emision');
        $lastOrder = Order::max('emision');

        $ref = match (true) {
            $lastShop !== null && $lastOrder !== null => Carbon::parse(max($lastShop, $lastOrder)),
            $lastShop !== null => Carbon::parse($lastShop),
            $lastOrder !== null => Carbon::parse($lastOrder),
            default => now()->subMonth(),
        };

        return [$ref->year, $ref->month];
    }
}
