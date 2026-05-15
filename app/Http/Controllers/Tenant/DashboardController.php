<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
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

        $company = Company::find($companyId, ['id', 'ruc', 'name', 'matrix_address', 'phone', 'email', 'type_declaration', 'accounting', 'retention_agent', 'phantom_taxpayer']);

        return Inertia::render('Tenant/Dashboard', [
            'company' => $company,
            'month' => $this->periodStats($companyId, $monthStart, $monthEnd),
            'year' => $this->periodStats($companyId, $yearStart, $yearEnd),
            'monthLabel' => $ref->format('Y-m'),
            'yearLabel' => $ref->format('Y'),
            'trend' => $this->monthlyTrend($companyId),
            'topProviders' => $this->topProviders($companyId, $yearStart, $yearEnd),
        ]);
    }

    /**
     * SQL expression that sums a column, negating rows whose voucher type is nota de crédito.
     *
     * @param  string  $column  e.g. 'total' or 'iva5+iva8+iva12+iva15'
     */
    private function signedSum(string $column): string
    {
        return "COALESCE(SUM(CASE WHEN vt.code IN ('04','23') THEN -({$column}) ELSE ({$column}) END), 0)";
    }

    /**
     * @return array{sales: array{count: int, total: float, iva: float}, purchases: array{count: int, total: float, iva: float}}
     *
     * Note: `total` contains the sum of sub_total (excluding IVA). Notas de crédito are subtracted.
     */
    private function periodStats(int $companyId, Carbon $from, Carbon $to): array
    {
        $totalExpr = $this->signedSum('sub_total');
        $ivaExpr = $this->signedSum('iva5+iva8+iva12+iva15');

        $sales = Order::where('orders.company_id', $companyId)
            ->whereBetween('orders.emision', [$from->toDateString(), $to->toDateString()])
            ->join('voucher_types as vt', 'vt.id', '=', 'orders.voucher_type_id')
            ->selectRaw("COUNT(*) as count, {$totalExpr} as total, {$ivaExpr} as iva")
            ->first();

        $purchases = Shop::where('shops.company_id', $companyId)
            ->whereBetween('shops.emision', [$from->toDateString(), $to->toDateString()])
            ->join('voucher_types as vt', 'vt.id', '=', 'shops.voucher_type_id')
            ->selectRaw("COUNT(*) as count, {$totalExpr} as total, {$ivaExpr} as iva")
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
        $totalExpr = $this->signedSum('sub_total');

        $sales = Order::where('orders.company_id', $companyId)
            ->where('orders.emision', '>=', $from)
            ->join('voucher_types as vt', 'vt.id', '=', 'orders.voucher_type_id')
            ->selectRaw("TO_CHAR(orders.emision, 'YYYY-MM') as month, {$totalExpr} as total")
            ->groupByRaw("TO_CHAR(orders.emision, 'YYYY-MM')")
            ->orderBy('month')
            ->pluck('total', 'month');

        $purchases = Shop::where('shops.company_id', $companyId)
            ->where('shops.emision', '>=', $from)
            ->join('voucher_types as vt', 'vt.id', '=', 'shops.voucher_type_id')
            ->selectRaw("TO_CHAR(shops.emision, 'YYYY-MM') as month, {$totalExpr} as total")
            ->groupByRaw("TO_CHAR(shops.emision, 'YYYY-MM')")
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
        $totalExpr = $this->signedSum('sub_total');

        return Shop::where('shops.company_id', $companyId)
            ->whereBetween('shops.emision', [$from->toDateString(), $to->toDateString()])
            ->join('voucher_types as vt', 'vt.id', '=', 'shops.voucher_type_id')
            ->with('contact:id,name,identification')
            ->selectRaw("contact_id, {$totalExpr} as total, COUNT(*) as count")
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
