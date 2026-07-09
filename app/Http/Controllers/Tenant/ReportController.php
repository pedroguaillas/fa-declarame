<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\OrdersByClientExport;
use App\Exports\OrdersByRetentionExport;
use App\Exports\OrdersByVoucherTypeExport;
use App\Exports\ShopsByAccountExport;
use App\Exports\ShopsByProviderExport;
use App\Exports\ShopsByRetentionExport;
use App\Exports\ShopsByVoucherTypeExport;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\Order;
use App\Models\Tenant\Shop;
use App\Models\Tenant\ShopItem;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        return Excel::download(new ShopsByAccountExport($rows, $filters, currentTenant()->logo_path, $this->currentCompanyName()), 'compras-por-cuentas.xlsx');
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

        return Excel::download(new ShopsByRetentionExport($rows, currentTenant()->logo_path, $this->currentCompanyName()), 'compras-por-retenciones.xlsx');
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

        return Excel::download(new ShopsByVoucherTypeExport($rows, $filters, currentTenant()->logo_path, $this->currentCompanyName()), 'compras-por-tipo-comprobante.xlsx');
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

        return Excel::download(new ShopsByProviderExport($rows, $filters, currentTenant()->logo_path, $this->currentCompanyName()), 'compras-por-proveedor.xlsx');
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

        return Excel::download(new OrdersByVoucherTypeExport($rows, currentTenant()->logo_path, $this->currentCompanyName()), 'ventas-por-tipo-comprobante.xlsx');
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

        return Excel::download(new OrdersByClientExport($rows, $filters, currentTenant()->logo_path, $this->currentCompanyName()), 'ventas-por-cliente.xlsx');
    }

    public function ordersByRetention(Request $request): Response
    {
        $filters = $this->resolvedOrderFilters($request);

        return Inertia::render('Tenant/Reports/OrdersByRetention', [
            'rows' => $this->ordersByRetentionRows((int) session('current_company_id'), $filters),
            'filters' => $filters,
        ]);
    }

    public function exportOrdersByRetention(Request $request): BinaryFileResponse
    {
        $filters = $this->resolvedOrderFilters($request);
        $rows = $this->ordersByRetentionRows((int) session('current_company_id'), $filters)->toArray();

        return Excel::download(new OrdersByRetentionExport($rows, currentTenant()->logo_path, $this->currentCompanyName()), 'ventas-por-retenciones.xlsx');
    }

    private function currentCompanyName(): ?string
    {
        return Company::find(session('current_company_id'))?->name;
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
        $sign = $this->signSql();

        $rows = Order::query()
            ->join('voucher_types AS vt', 'vt.id', 'orders.voucher_type_id')
            ->leftJoinSub($this->retentionTotalsSub('order_retention_items', 'order_id'), 'ret', 'ret.order_id', 'orders.id')
            ->where('orders.company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->where('orders.emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->where('orders.emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('orders.state', 'AUTORIZADO'))
            ->groupBy('orders.voucher_type_id', 'vt.code', 'vt.description')
            ->selectRaw("
                vt.code AS vt_code,
                vt.description AS vt_description,
                COUNT(*) AS doc_count,
                SUM({$sign} * orders.sub_total) AS subtotal,
                SUM({$sign} * (orders.iva5 + orders.iva12 + orders.iva15)) AS iva,
                SUM({$sign} * orders.total) AS total,
                SUM(COALESCE(ret.total_retention, 0)) AS retentions
            ")
            ->get();

        return $rows
            ->map(fn ($r) => [
                'code' => $r->vt_code,
                'description' => $r->vt_description,
                'count' => (int) $r->doc_count,
                'subtotal' => round((float) $r->subtotal, 2),
                'iva' => round((float) $r->iva, 2),
                'total' => round((float) $r->total, 2),
                'retentions' => round((float) $r->retentions, 2),
                'a_cobrar' => round((float) $r->total - (float) $r->retentions, 2),
            ])
            ->sortBy('code')
            ->values();
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function ordersByClientRows(int $companyId, array $filters): Collection
    {
        $sign = $this->signSql();

        $rows = Order::query()
            ->join('voucher_types AS vt', 'vt.id', 'orders.voucher_type_id')
            ->join('contacts AS c', 'c.id', 'orders.contact_id')
            ->leftJoinSub($this->retentionTotalsSub('order_retention_items', 'order_id'), 'ret', 'ret.order_id', 'orders.id')
            ->where('orders.company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->where('orders.emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->where('orders.emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('orders.state', 'AUTORIZADO'))
            ->groupBy('orders.contact_id', 'c.identification', 'c.name')
            ->selectRaw("
                c.identification AS identification,
                c.name AS name,
                SUM({$sign} * orders.sub_total) AS subtotal,
                SUM({$sign} * orders.no_iva) AS no_iva,
                SUM({$sign} * orders.exempt) AS exempt,
                SUM({$sign} * orders.base0) AS base0,
                SUM({$sign} * orders.base5) AS base5,
                SUM({$sign} * orders.base12) AS base12,
                SUM({$sign} * orders.base15) AS base15,
                SUM({$sign} * orders.iva5) AS iva5,
                SUM({$sign} * orders.iva12) AS iva12,
                SUM({$sign} * orders.iva15) AS iva15,
                SUM({$sign} * orders.total) AS total,
                SUM(COALESCE(ret.total_retention, 0)) AS retentions
            ")
            ->get();

        return $rows
            ->map(fn ($r) => [
                'identification' => $r->identification ?? '—',
                'name' => $r->name ?? 'Sin cliente',
                'subtotal' => round((float) $r->subtotal, 2),
                'iva' => round((float) $r->iva5 + (float) $r->iva12 + (float) $r->iva15, 2),
                'no_iva' => round((float) $r->no_iva, 2),
                'exempt' => round((float) $r->exempt, 2),
                'base0' => round((float) $r->base0, 2),
                'base5' => round((float) $r->base5, 2),
                'base12' => round((float) $r->base12, 2),
                'base15' => round((float) $r->base15, 2),
                'iva5' => round((float) $r->iva5, 2),
                'iva12' => round((float) $r->iva12, 2),
                'iva15' => round((float) $r->iva15, 2),
                'total' => round((float) $r->total, 2),
                'retentions' => round((float) $r->retentions, 2),
                'a_cobrar' => round((float) $r->total - (float) $r->retentions, 2),
            ])
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
        $sign = $this->signSql();
        $bucket = fn (string $column, int $pct): string => "SUM(CASE WHEN shop_items.tax_percentage = {$pct} THEN {$sign} * shop_items.{$column} ELSE 0 END)";

        $rows = ShopItem::query()
            ->join('shops', 'shops.id', '=', 'shop_items.shop_id')
            ->join('voucher_types AS vt', 'vt.id', '=', 'shops.voucher_type_id')
            ->join('product_accounts AS pa', function ($join) use ($companyId) {
                $join->on('pa.product_id', '=', 'shop_items.product_id')
                    ->where('pa.company_id', '=', $companyId);
            })
            ->join('accounts AS acc', 'acc.id', '=', 'pa.account_id')
            ->where('shop_items.total', '>', 0)
            ->where('shops.company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->where('shops.emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->where('shops.emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('shops.state', 'AUTORIZADO'))
            ->groupBy('pa.account_id', 'acc.code', 'acc.name')
            ->selectRaw(implode(', ', [
                'acc.code AS account_code',
                'acc.name AS account_name',
                "SUM({$sign} * shop_items.total) AS subtotal",
                "SUM({$sign} * shop_items.tax_value) AS iva",
                $bucket('total', 0).' AS base0',
                $bucket('total', 5).' AS base5',
                $bucket('total', 8).' AS base8',
                $bucket('total', 12).' AS base12',
                $bucket('total', 15).' AS base15',
                $bucket('tax_value', 5).' AS iva5',
                $bucket('tax_value', 8).' AS iva8',
                $bucket('tax_value', 12).' AS iva12',
                $bucket('tax_value', 15).' AS iva15',
            ]))
            ->get();

        return $rows
            ->map(function ($r) {
                $subtotal = round((float) $r->subtotal, 2);
                $iva = round((float) $r->iva, 2);

                return [
                    'account_code' => $r->account_code,
                    'account_name' => $r->account_name,
                    'subtotal' => $subtotal,
                    'base0' => round((float) $r->base0, 2),
                    'base5' => round((float) $r->base5, 2),
                    'base8' => round((float) $r->base8, 2),
                    'base12' => round((float) $r->base12, 2),
                    'base15' => round((float) $r->base15, 2),
                    'iva5' => round((float) $r->iva5, 2),
                    'iva8' => round((float) $r->iva8, 2),
                    'iva12' => round((float) $r->iva12, 2),
                    'iva15' => round((float) $r->iva15, 2),
                    'iva' => $iva,
                    'total' => round($subtotal + $iva, 2),
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
        $sign = $this->signSql();

        $rows = Shop::query()
            ->join('voucher_types AS vt', 'vt.id', 'shops.voucher_type_id')
            ->leftJoinSub($this->retentionTotalsSub('shop_retention_items', 'shop_id'), 'ret', 'ret.shop_id', 'shops.id')
            ->where('shops.company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->where('shops.emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->where('shops.emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('shops.state', 'AUTORIZADO'))
            ->groupBy('shops.voucher_type_id', 'vt.code', 'vt.description')
            ->selectRaw("
                vt.code AS vt_code,
                vt.description AS vt_description,
                COUNT(*) AS doc_count,
                SUM({$sign} * shops.sub_total) AS subtotal,
                SUM({$sign} * shops.no_iva) AS no_iva,
                SUM({$sign} * shops.exempt) AS exempt,
                SUM({$sign} * shops.base0) AS base0,
                SUM({$sign} * shops.base5) AS base5,
                SUM({$sign} * shops.base8) AS base8,
                SUM({$sign} * shops.base12) AS base12,
                SUM({$sign} * shops.base15) AS base15,
                SUM({$sign} * shops.iva5) AS iva5,
                SUM({$sign} * shops.iva8) AS iva8,
                SUM({$sign} * shops.iva12) AS iva12,
                SUM({$sign} * shops.iva15) AS iva15,
                SUM({$sign} * shops.total) AS total,
                SUM(COALESCE(ret.total_retention, 0)) AS retentions
            ")
            ->get();

        return $rows
            ->map(fn ($r) => [
                'code' => $r->vt_code,
                'description' => $r->vt_description,
                'count' => (int) $r->doc_count,
                'subtotal' => round((float) $r->subtotal, 2),
                'no_iva' => round((float) $r->no_iva, 2),
                'exempt' => round((float) $r->exempt, 2),
                'base0' => round((float) $r->base0, 2),
                'base5' => round((float) $r->base5, 2),
                'base8' => round((float) $r->base8, 2),
                'base12' => round((float) $r->base12, 2),
                'base15' => round((float) $r->base15, 2),
                'iva5' => round((float) $r->iva5, 2),
                'iva8' => round((float) $r->iva8, 2),
                'iva12' => round((float) $r->iva12, 2),
                'iva15' => round((float) $r->iva15, 2),
                'total' => round((float) $r->total, 2),
                'retentions' => round((float) $r->retentions, 2),
                'a_pagar' => round((float) $r->total - (float) $r->retentions, 2),
            ])
            ->sortBy('code')
            ->values();
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function shopsByProviderRows(int $companyId, array $filters): Collection
    {
        $sign = $this->signSql();

        $rows = Shop::query()
            ->join('voucher_types AS vt', 'vt.id', 'shops.voucher_type_id')
            ->join('contacts AS c', 'c.id', 'shops.contact_id')
            ->leftJoinSub($this->retentionTotalsSub('shop_retention_items', 'shop_id'), 'ret', 'ret.shop_id', 'shops.id')
            ->where('shops.company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->where('shops.emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->where('shops.emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('shops.state', 'AUTORIZADO'))
            ->groupBy('shops.contact_id', 'c.identification', 'c.name')
            ->selectRaw("
                c.identification AS identification,
                c.name AS name,
                SUM({$sign} * shops.sub_total) AS subtotal,
                SUM({$sign} * shops.no_iva) AS no_iva,
                SUM({$sign} * shops.exempt) AS exempt,
                SUM({$sign} * shops.base0) AS base0,
                SUM({$sign} * shops.base5) AS base5,
                SUM({$sign} * shops.base8) AS base8,
                SUM({$sign} * shops.base12) AS base12,
                SUM({$sign} * shops.base15) AS base15,
                SUM({$sign} * shops.iva5) AS iva5,
                SUM({$sign} * shops.iva8) AS iva8,
                SUM({$sign} * shops.iva12) AS iva12,
                SUM({$sign} * shops.iva15) AS iva15,
                SUM({$sign} * shops.total) AS total,
                SUM(COALESCE(ret.total_retention, 0)) AS retentions
            ")
            ->get();

        return $rows
            ->map(fn ($r) => [
                'identification' => $r->identification ?? '—',
                'name' => $r->name ?? 'Sin proveedor',
                'subtotal' => round((float) $r->subtotal, 2),
                'iva' => round((float) $r->iva5 + (float) $r->iva8 + (float) $r->iva12 + (float) $r->iva15, 2),
                'no_iva' => round((float) $r->no_iva, 2),
                'exempt' => round((float) $r->exempt, 2),
                'base0' => round((float) $r->base0, 2),
                'base5' => round((float) $r->base5, 2),
                'base8' => round((float) $r->base8, 2),
                'base12' => round((float) $r->base12, 2),
                'base15' => round((float) $r->base15, 2),
                'iva5' => round((float) $r->iva5, 2),
                'iva8' => round((float) $r->iva8, 2),
                'iva12' => round((float) $r->iva12, 2),
                'iva15' => round((float) $r->iva15, 2),
                'total' => round((float) $r->total, 2),
                'retentions' => round((float) $r->retentions, 2),
                'a_pagar' => round((float) $r->total - (float) $r->retentions, 2),
            ])
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function shopsByRetentionRows(int $companyId, array $filters): Collection
    {
        return $this->retentionRows('shop_retention_items', 'shops', 'shop_id', $companyId, $filters);
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function ordersByRetentionRows(int $companyId, array $filters): Collection
    {
        return $this->retentionRows('order_retention_items', 'orders', 'order_id', $companyId, $filters);
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null, only_authorized?: bool}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function retentionRows(string $itemTable, string $documentTable, string $documentKey, int $companyId, array $filters): Collection
    {
        $rows = DB::table($itemTable.' AS items')
            ->join('retentions AS r', 'r.id', '=', 'items.retention_id')
            ->join($documentTable.' AS d', 'd.id', '=', 'items.'.$documentKey)
            ->where('r.type', 'RENTA')
            ->where('d.company_id', $companyId)
            ->when($filters['start_date'] ?? null, fn ($q, $d) => $q->where('d.emision', '>=', $d))
            ->when($filters['end_date'] ?? null, fn ($q, $d) => $q->where('d.emision', '<=', $d))
            ->when($filters['only_authorized'] ?? true, fn ($q) => $q->where('d.state', 'AUTORIZADO'))
            ->groupBy('items.retention_id', 'r.code', 'r.description', 'r.percentage')
            ->selectRaw('
                r.code AS code,
                r.description AS description,
                r.percentage AS percentage,
                SUM(items.base) AS base,
                SUM(items.value) AS value
            ')
            ->get();

        return $rows
            ->map(fn ($r) => [
                'code' => $r->code,
                'description' => $r->description,
                'percentage' => (float) $r->percentage,
                'base' => round((float) $r->base, 2),
                'value' => round((float) $r->value, 2),
            ])
            ->sortBy('code')
            ->values();
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
}
