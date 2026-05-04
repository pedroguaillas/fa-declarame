<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shop;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $companyId = (int) session('current_company_id');

        $lastShop = Shop::where('company_id', $companyId)->max('emision');
        $lastOrder = Order::where('company_id', $companyId)->max('emision');
        $lastEmision = max($lastShop ?? '', $lastOrder ?? '');
        $ref = $lastEmision ? Carbon::parse($lastEmision) : Carbon::now();

        $monthStart = $ref->copy()->startOfMonth();
        $monthEnd = $ref->copy()->endOfMonth();
        $yearStart = $ref->copy()->startOfYear();
        $yearEnd = $ref->copy()->endOfYear();

        return Inertia::render('Tenant/Dashboard', [
            'month' => $this->periodStats($companyId, $monthStart, $monthEnd),
            'year' => $this->periodStats($companyId, $yearStart, $yearEnd),
            'monthLabel' => $ref->format('Y-m'),
            'yearLabel' => $ref->format('Y'),
            'trend' => $this->monthlyTrend($companyId),
            'topProviders' => $this->topProviders($companyId, $yearStart, $yearEnd),
        ]);
    }

    /**
     * @return array{sales: array{count: int, total: float, iva: float}, purchases: array{count: int, total: float, iva: float}}
     */
    private function periodStats(int $companyId, Carbon $from, Carbon $to): array
    {
        $sales = Order::where('company_id', $companyId)
            ->whereBetween('emision', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(iva5+iva8+iva12+iva15), 0) as iva')
            ->first();

        $purchases = Shop::where('company_id', $companyId)
            ->whereBetween('emision', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(iva5+iva8+iva12+iva15), 0) as iva')
            ->first();

        return [
            'sales' => [
                'count' => (int) $sales->count,
                'total' => round((float) $sales->total, 2),
                'iva' => round((float) $sales->iva, 2),
            ],
            'purchases' => [
                'count' => (int) $purchases->count,
                'total' => round((float) $purchases->total, 2),
                'iva' => round((float) $purchases->iva, 2),
            ],
        ];
    }

    /**
     * @return array<int, array{month: string, sales: float, purchases: float}>
     */
    private function monthlyTrend(int $companyId): array
    {
        $from = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();

        $sales = Order::where('company_id', $companyId)
            ->where('emision', '>=', $from)
            ->selectRaw("TO_CHAR(emision, 'YYYY-MM') as month, COALESCE(SUM(total), 0) as total")
            ->groupByRaw("TO_CHAR(emision, 'YYYY-MM')")
            ->orderBy('month')
            ->pluck('total', 'month');

        $purchases = Shop::where('company_id', $companyId)
            ->where('emision', '>=', $from)
            ->selectRaw("TO_CHAR(emision, 'YYYY-MM') as month, COALESCE(SUM(total), 0) as total")
            ->groupByRaw("TO_CHAR(emision, 'YYYY-MM')")
            ->orderBy('month')
            ->pluck('total', 'month');

        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $key = Carbon::now()->subMonths($i)->format('Y-m');
            $months[] = [
                'month' => $key,
                'sales' => round((float) ($sales[$key] ?? 0), 2),
                'purchases' => round((float) ($purchases[$key] ?? 0), 2),
            ];
        }

        return $months;
    }

    /**
     * @return array<int, array{name: string, identification: string, total: float, count: int}>
     */
    private function topProviders(int $companyId, Carbon $from, Carbon $to): array
    {
        return Shop::where('company_id', $companyId)
            ->whereBetween('emision', [$from->toDateString(), $to->toDateString()])
            ->with('contact:id,name,identification')
            ->selectRaw('contact_id, COALESCE(SUM(total), 0) as total, COUNT(*) as count')
            ->groupBy('contact_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($shop) => [
                'name' => $shop->contact?->name ?? '—',
                'identification' => $shop->contact?->identification ?? '—',
                'total' => round((float) $shop->total, 2),
                'count' => (int) $shop->count,
            ])
            ->toArray();
    }
}
