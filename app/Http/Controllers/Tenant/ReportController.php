<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\OrdersByClientExport;
use App\Exports\OrdersByVoucherTypeExport;
use App\Exports\ShopsByAccountExport;
use App\Exports\ShopsByProviderExport;
use App\Exports\ShopsByRetentionExport;
use App\Exports\ShopsByVoucherTypeExport;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shop;
use App\Models\Tenant\ShopRetentionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tenant/Reports/Index');
    }

    public function shopsByAccount(Request $request): Response
    {
        $filters = $this->resolvedFilters($request);

        return Inertia::render('Tenant/Reports/ShopsByAccount', [
            'rows' => $this->shopsByAccountRows((int) session('current_company_id'), $filters),
            'filters' => $filters,
        ]);
    }

    public function exportShopsByAccount(Request $request): BinaryFileResponse
    {
        $filters = $this->resolvedFilters($request);
        $rows = $this->shopsByAccountRows((int) session('current_company_id'), $filters)->toArray();

        return Excel::download(new ShopsByAccountExport($rows, $filters), 'compras-por-cuentas.xlsx');
    }

    public function shopsByRetention(Request $request): Response
    {
        $filters = $this->resolvedFilters($request);

        return Inertia::render('Tenant/Reports/ShopsByRetention', [
            'rows' => $this->shopsByRetentionRows((int) session('current_company_id'), $filters),
            'filters' => $filters,
        ]);
    }

    public function exportShopsByRetention(Request $request): BinaryFileResponse
    {
        $filters = $this->resolvedFilters($request);
        $rows = $this->shopsByRetentionRows((int) session('current_company_id'), $filters)->toArray();

        return Excel::download(new ShopsByRetentionExport($rows), 'compras-por-retenciones.xlsx');
    }

    public function shopsByVoucherType(Request $request): Response
    {
        $filters = $this->resolvedFilters($request);

        return Inertia::render('Tenant/Reports/ShopsByVoucherType', [
            'rows' => $this->shopsByVoucherTypeRows((int) session('current_company_id'), $filters),
            'filters' => $filters,
        ]);
    }

    public function exportShopsByVoucherType(Request $request): BinaryFileResponse
    {
        $filters = $this->resolvedFilters($request);
        $rows = $this->shopsByVoucherTypeRows((int) session('current_company_id'), $filters)->toArray();

        return Excel::download(new ShopsByVoucherTypeExport($rows, $filters), 'compras-por-tipo-comprobante.xlsx');
    }

    public function shopsByProvider(Request $request): Response
    {
        $filters = $this->resolvedFilters($request);

        return Inertia::render('Tenant/Reports/ShopsByProvider', [
            'rows' => $this->shopsByProviderRows((int) session('current_company_id'), $filters),
            'filters' => $filters,
        ]);
    }

    public function exportShopsByProvider(Request $request): BinaryFileResponse
    {
        $filters = $this->resolvedFilters($request);
        $rows = $this->shopsByProviderRows((int) session('current_company_id'), $filters)->toArray();

        return Excel::download(new ShopsByProviderExport($rows, $filters), 'compras-por-proveedor.xlsx');
    }

    public function ordersByVoucherType(Request $request): Response
    {
        $filters = $this->resolvedOrderFilters($request);

        return Inertia::render('Tenant/Reports/OrdersByVoucherType', [
            'rows' => $this->ordersByVoucherTypeRows((int) session('current_company_id'), $filters),
            'filters' => $filters,
        ]);
    }

    public function exportOrdersByVoucherType(Request $request): BinaryFileResponse
    {
        $filters = $this->resolvedOrderFilters($request);
        $rows = $this->ordersByVoucherTypeRows((int) session('current_company_id'), $filters)->toArray();

        return Excel::download(new OrdersByVoucherTypeExport($rows), 'ventas-por-tipo-comprobante.xlsx');
    }

    public function ordersByClient(Request $request): Response
    {
        $filters = $this->resolvedOrderFilters($request);

        return Inertia::render('Tenant/Reports/OrdersByClient', [
            'rows' => $this->ordersByClientRows((int) session('current_company_id'), $filters),
            'filters' => $filters,
        ]);
    }

    public function exportOrdersByClient(Request $request): BinaryFileResponse
    {
        $filters = $this->resolvedOrderFilters($request);
        $rows = $this->ordersByClientRows((int) session('current_company_id'), $filters)->toArray();

        return Excel::download(new OrdersByClientExport($rows, $filters), 'ventas-por-cliente.xlsx');
    }

    /** @return array{start_date: string, end_date: string, only_authorized: bool} */
    private function resolvedOrderFilters(Request $request): array
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $onlyAuthorized = $request->boolean('only_authorized', true);

        if ($request->filled('start_date') || $request->filled('end_date')) {
            return array_merge($request->only('start_date', 'end_date'), ['only_authorized' => $onlyAuthorized]);
        }

        $lastEmision = Order::max('emision');
        $ref = $lastEmision ? Carbon::parse($lastEmision) : now();

        return [
            'start_date' => $ref->copy()->startOfMonth()->format('Y-m-d'),
            'end_date' => $ref->copy()->endOfMonth()->format('Y-m-d'),
            'only_authorized' => $onlyAuthorized,
        ];
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function ordersByVoucherTypeRows(int $companyId, array $filters): Collection
    {
        $orders = Order::query()
            ->join('voucher_types AS vt', 'vt.id', 'orders.voucher_type_id')
            ->select(['orders.id', 'orders.voucher_type_id', 'vt.code as vt_code', 'vt.description as vt_description', 'sub_total', 'iva5', 'iva12', 'iva15', 'total'])
            ->withSum('retentionItems as total_retention', 'value')
            ->where('company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('orders.state', 'AUTORIZADO'))
            ->get();

        return $orders
            ->groupBy('voucher_type_id')
            ->map(function ($group) {
                $first = $group->first();
                $sign = $first->vt_code === '04' ? -1 : 1;
                $subtotal = $group->sum(fn ($o) => (float) $o->sub_total);
                $iva = $group->sum(fn ($o) => (float) $o->iva5 + (float) $o->iva12 + (float) $o->iva15);
                $total = $group->sum(fn ($o) => (float) $o->total);
                $retentions = $group->sum(fn ($o) => (float) $o->total_retention);

                return [
                    'code' => $first->vt_code,
                    'description' => $first->vt_description,
                    'count' => $group->count(),
                    'subtotal' => round($sign * $subtotal, 2),
                    'iva' => round($sign * $iva, 2),
                    'total' => round($sign * $total, 2),
                    'retentions' => round($retentions, 2),
                    'a_cobrar' => round($sign * $total - $retentions, 2),
                ];
            })
            ->sortBy('code')
            ->values();
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function ordersByClientRows(int $companyId, array $filters): Collection
    {
        $orders = Order::query()
            ->join('voucher_types AS vt', 'vt.id', 'orders.voucher_type_id')
            ->select(['orders.id', 'orders.contact_id', 'vt.code as vt_code', 'sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base12', 'base15', 'iva5', 'iva12', 'iva15', 'total'])
            ->with('contact:id,identification,name')
            ->withSum('retentionItems as total_retention', 'value')
            ->where('company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('orders.state', 'AUTORIZADO'))
            ->get();

        return $orders
            ->groupBy('contact_id')
            ->map(function ($group) {
                $first = $group->first();
                $sign = fn ($o) => $o->vt_code === '04' ? -1 : 1;
                $subtotal = $group->sum(fn ($o) => $sign($o) * (float) $o->sub_total);
                $noIva = $group->sum(fn ($o) => $sign($o) * (float) $o->no_iva);
                $exempt = $group->sum(fn ($o) => $sign($o) * (float) $o->exempt);
                $base0 = $group->sum(fn ($o) => $sign($o) * (float) $o->base0);
                $base5 = $group->sum(fn ($o) => $sign($o) * (float) $o->base5);
                $base12 = $group->sum(fn ($o) => $sign($o) * (float) $o->base12);
                $base15 = $group->sum(fn ($o) => $sign($o) * (float) $o->base15);
                $iva5 = $group->sum(fn ($o) => $sign($o) * (float) $o->iva5);
                $iva12 = $group->sum(fn ($o) => $sign($o) * (float) $o->iva12);
                $iva15 = $group->sum(fn ($o) => $sign($o) * (float) $o->iva15);
                $total = $group->sum(fn ($o) => $sign($o) * (float) $o->total);
                $retentions = $group->sum(fn ($o) => (float) $o->total_retention);

                return [
                    'identification' => $first->contact?->identification ?? '—',
                    'name' => $first->contact?->name ?? 'Sin cliente',
                    'subtotal' => round($subtotal, 2),
                    'iva' => round($iva5 + $iva12 + $iva15, 2),
                    'no_iva' => round($noIva, 2),
                    'exempt' => round($exempt, 2),
                    'base0' => round($base0, 2),
                    'base5' => round($base5, 2),
                    'base12' => round($base12, 2),
                    'base15' => round($base15, 2),
                    'iva5' => round($iva5, 2),
                    'iva12' => round($iva12, 2),
                    'iva15' => round($iva15, 2),
                    'total' => round($total, 2),
                    'retentions' => round($retentions, 2),
                    'a_cobrar' => round($total - $retentions, 2),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /** @return array{start_date: string, end_date: string, only_authorized: bool} */
    private function resolvedFilters(Request $request): array
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $onlyAuthorized = $request->boolean('only_authorized', true);

        if ($request->filled('start_date') || $request->filled('end_date')) {
            return array_merge($request->only('start_date', 'end_date'), ['only_authorized' => $onlyAuthorized]);
        }

        $lastEmision = Shop::max('emision');
        $ref = $lastEmision ? Carbon::parse($lastEmision) : now();

        return [
            'start_date' => $ref->copy()->startOfMonth()->format('Y-m-d'),
            'end_date' => $ref->copy()->endOfMonth()->format('Y-m-d'),
            'only_authorized' => $onlyAuthorized,
        ];
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function shopsByAccountRows(int $companyId, array $filters): Collection
    {
        $shops = Shop::query()
            ->join('voucher_types AS vt', 'vt.id', 'shops.voucher_type_id')
            ->select(['shops.id', 'shops.account_id', 'vt.code as vt_code', 'sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'total'])
            ->with('account:id,code,name')
            ->withSum('retentionItems as total_retention', 'value')
            ->where('company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('shops.state', 'AUTORIZADO'))
            ->get();

        return $shops
            ->groupBy('account_id')
            ->map(function ($group) {
                $first = $group->first();
                $sign = fn ($s) => $s->vt_code === '04' ? -1 : 1;
                $subtotal = $group->sum(fn ($s) => $sign($s) * (float) $s->sub_total);
                $noIva = $group->sum(fn ($s) => $sign($s) * (float) $s->no_iva);
                $exempt = $group->sum(fn ($s) => $sign($s) * (float) $s->exempt);
                $base0 = $group->sum(fn ($s) => $sign($s) * (float) $s->base0);
                $base5 = $group->sum(fn ($s) => $sign($s) * (float) $s->base5);
                $base8 = $group->sum(fn ($s) => $sign($s) * (float) $s->base8);
                $base12 = $group->sum(fn ($s) => $sign($s) * (float) $s->base12);
                $base15 = $group->sum(fn ($s) => $sign($s) * (float) $s->base15);
                $iva5 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva5);
                $iva8 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva8);
                $iva12 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva12);
                $iva15 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva15);
                $total = $group->sum(fn ($s) => $sign($s) * (float) $s->total);
                $retentions = $group->sum(fn ($s) => (float) $s->total_retention);

                return [
                    'account_code' => $first->account?->code,
                    'account_name' => $first->account?->name ?? 'Sin cuenta asignada',
                    'subtotal' => round($subtotal, 2),
                    'no_iva' => round($noIva, 2),
                    'exempt' => round($exempt, 2),
                    'base0' => round($base0, 2),
                    'base5' => round($base5, 2),
                    'base8' => round($base8, 2),
                    'base12' => round($base12, 2),
                    'base15' => round($base15, 2),
                    'iva5' => round($iva5, 2),
                    'iva8' => round($iva8, 2),
                    'iva12' => round($iva12, 2),
                    'iva15' => round($iva15, 2),
                    'total' => round($total, 2),
                    'retentions' => round($retentions, 2),
                    'a_pagar' => round($total - $retentions, 2),
                ];
            })
            ->sortBy('account_code')
            ->values();
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function shopsByVoucherTypeRows(int $companyId, array $filters): Collection
    {
        $shops = Shop::query()
            ->join('voucher_types AS vt', 'vt.id', 'shops.voucher_type_id')
            ->select(['shops.id', 'shops.voucher_type_id', 'vt.code as vt_code', 'vt.description as vt_description', 'sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'total'])
            ->withSum('retentionItems as total_retention', 'value')
            ->where('company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('shops.state', 'AUTORIZADO'))
            ->get();

        return $shops
            ->groupBy('voucher_type_id')
            ->map(function ($group) {
                $first = $group->first();
                $sign = $first->vt_code === '04' ? -1 : 1;
                $subtotal = $group->sum(fn ($s) => (float) $s->sub_total);
                $noIva = $group->sum(fn ($s) => (float) $s->no_iva);
                $exempt = $group->sum(fn ($s) => (float) $s->exempt);
                $base0 = $group->sum(fn ($s) => (float) $s->base0);
                $base5 = $group->sum(fn ($s) => (float) $s->base5);
                $base8 = $group->sum(fn ($s) => (float) $s->base8);
                $base12 = $group->sum(fn ($s) => (float) $s->base12);
                $base15 = $group->sum(fn ($s) => (float) $s->base15);
                $iva5 = $group->sum(fn ($s) => (float) $s->iva5);
                $iva8 = $group->sum(fn ($s) => (float) $s->iva8);
                $iva12 = $group->sum(fn ($s) => (float) $s->iva12);
                $iva15 = $group->sum(fn ($s) => (float) $s->iva15);
                $total = $group->sum(fn ($s) => (float) $s->total);
                $retentions = $group->sum(fn ($s) => (float) $s->total_retention);

                return [
                    'code' => $first->vt_code,
                    'description' => $first->vt_description,
                    'count' => $group->count(),
                    'subtotal' => round($sign * $subtotal, 2),
                    'no_iva' => round($sign * $noIva, 2),
                    'exempt' => round($sign * $exempt, 2),
                    'base0' => round($sign * $base0, 2),
                    'base5' => round($sign * $base5, 2),
                    'base8' => round($sign * $base8, 2),
                    'base12' => round($sign * $base12, 2),
                    'base15' => round($sign * $base15, 2),
                    'iva5' => round($sign * $iva5, 2),
                    'iva8' => round($sign * $iva8, 2),
                    'iva12' => round($sign * $iva12, 2),
                    'iva15' => round($sign * $iva15, 2),
                    'total' => round($sign * $total, 2),
                    'retentions' => round($retentions, 2),
                    'a_pagar' => round($sign * $total - $retentions, 2),
                ];
            })
            ->sortBy('code')
            ->values();
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function shopsByProviderRows(int $companyId, array $filters): Collection
    {
        $shops = Shop::query()
            ->join('voucher_types AS vt', 'vt.id', 'shops.voucher_type_id')
            ->select(['shops.id', 'shops.contact_id', 'vt.code as vt_code', 'sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'total'])
            ->with('contact:id,identification,name')
            ->withSum('retentionItems as total_retention', 'value')
            ->where('company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('shops.state', 'AUTORIZADO'))
            ->get();

        return $shops
            ->groupBy('contact_id')
            ->map(function ($group) {
                $first = $group->first();
                $sign = fn ($s) => $s->vt_code === '04' ? -1 : 1;
                $subtotal = $group->sum(fn ($s) => $sign($s) * (float) $s->sub_total);
                $noIva = $group->sum(fn ($s) => $sign($s) * (float) $s->no_iva);
                $exempt = $group->sum(fn ($s) => $sign($s) * (float) $s->exempt);
                $base0 = $group->sum(fn ($s) => $sign($s) * (float) $s->base0);
                $base5 = $group->sum(fn ($s) => $sign($s) * (float) $s->base5);
                $base8 = $group->sum(fn ($s) => $sign($s) * (float) $s->base8);
                $base12 = $group->sum(fn ($s) => $sign($s) * (float) $s->base12);
                $base15 = $group->sum(fn ($s) => $sign($s) * (float) $s->base15);
                $iva5 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva5);
                $iva8 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva8);
                $iva12 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva12);
                $iva15 = $group->sum(fn ($s) => $sign($s) * (float) $s->iva15);
                $total = $group->sum(fn ($s) => $sign($s) * (float) $s->total);
                $retentions = $group->sum(fn ($s) => (float) $s->total_retention);

                return [
                    'identification' => $first->contact?->identification ?? '—',
                    'name' => $first->contact?->name ?? 'Sin proveedor',
                    'subtotal' => round($subtotal, 2),
                    'iva' => round($iva5 + $iva8 + $iva12 + $iva15, 2),
                    'no_iva' => round($noIva, 2),
                    'exempt' => round($exempt, 2),
                    'base0' => round($base0, 2),
                    'base5' => round($base5, 2),
                    'base8' => round($base8, 2),
                    'base12' => round($base12, 2),
                    'base15' => round($base15, 2),
                    'iva5' => round($iva5, 2),
                    'iva8' => round($iva8, 2),
                    'iva12' => round($iva12, 2),
                    'iva15' => round($iva15, 2),
                    'total' => round($total, 2),
                    'retentions' => round($retentions, 2),
                    'a_pagar' => round($total - $retentions, 2),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function shopsByRetentionRows(int $companyId, array $filters): Collection
    {
        $items = ShopRetentionItem::query()
            ->with('retention:id,code,description,percentage')
            ->whereHas('retention', fn ($q) => $q->where('type', 'RENTA'))
            ->whereHas('shop', function ($q) use ($companyId, $filters) {
                $q->where('company_id', $companyId)
                    ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '>=', $d))
                    ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->whereDate('emision', '<=', $d))
                    ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('state', 'AUTORIZADO'));
            })
            ->get(['id', 'retention_id', 'base', 'percentage', 'value']);

        return $items
            ->groupBy('retention_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'code' => $first->retention?->code,
                    'description' => $first->retention?->description,
                    'percentage' => (float) $first->retention?->percentage,
                    'base' => round($group->sum(fn ($i) => (float) $i->base), 2),
                    'value' => round($group->sum(fn ($i) => (float) $i->value), 2),
                ];
            })
            ->sortBy('code')
            ->values();
    }
}
