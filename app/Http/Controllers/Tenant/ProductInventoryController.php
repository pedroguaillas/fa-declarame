<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\ProductInventoryExport;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Models\Tenant\ShopItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductInventoryController extends Controller
{
    /**
     * Inventario de productos comprados a proveedores: una fila por producto con
     * cantidades acumuladas, precio de la última compra y rango de precios pagados.
     */
    public function index(Request $request): Response
    {
        $filters = $this->resolvedFilters($request);

        $products = $this->inventoryRowsQuery($filters)
            ->paginate(50)
            ->withQueryString();

        $summary = $this->inventoryQuery($filters)
            ->selectRaw('COUNT(DISTINCT shop_items.product_id) AS products_count')
            ->selectRaw('COUNT(DISTINCT products.contact_id) AS suppliers_count')
            ->selectRaw('COALESCE(SUM(shop_items.total), 0) AS total_amount')
            ->first();

        return Inertia::render('Tenant/ProductInventory/Index', [
            'products' => $products,
            'filters' => $filters,
            'summary' => [
                'products_count' => (int) ($summary->products_count ?? 0),
                'suppliers_count' => (int) ($summary->suppliers_count ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $rows = $this->inventoryRowsQuery($this->resolvedFilters($request))
            ->get()
            ->map(fn (ShopItem $item): array => $item->getAttributes())
            ->all();

        return Excel::download(
            new ProductInventoryExport($rows, currentTenant()->logo_path, $this->currentCompanyName()),
            'inventario-de-productos.xlsx',
        );
    }

    /** @return array{search: string, from: string, to: string, sort: string} */
    private function resolvedFilters(Request $request): array
    {
        return [
            'search' => (string) $request->input('search', ''),
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
            'sort' => (string) $request->input('sort', ''),
        ];
    }

    /**
     * Una fila por producto: cantidades acumuladas, precios y última compra.
     *
     * @param  array{search: string, from: string, to: string, sort: string}  $filters
     */
    private function inventoryRowsQuery(array $filters): Builder
    {
        return $this->inventoryQuery($filters)
            ->groupBy(
                'products.id',
                'products.code',
                'products.aux_code',
                'products.description',
                'contacts.id',
                'contacts.identification',
                'contacts.name',
            )
            ->selectRaw('products.id')
            ->selectRaw('products.code')
            ->selectRaw('products.aux_code')
            ->selectRaw('products.description')
            ->selectRaw('contacts.id AS contact_id')
            ->selectRaw('contacts.identification AS contact_identification')
            ->selectRaw('contacts.name AS contact_name')
            ->selectRaw('SUM(shop_items.quantity) AS total_quantity')
            ->selectRaw('SUM(shop_items.total) AS total_amount')
            ->selectRaw('MIN(shop_items.unit_price) AS min_price')
            ->selectRaw('MAX(shop_items.unit_price) AS max_price')
            ->selectRaw('SUM(shop_items.total) / NULLIF(SUM(shop_items.quantity), 0) AS avg_price')
            ->selectRaw('(array_agg(shop_items.unit_price ORDER BY shops.emision DESC, shop_items.id DESC))[1] AS last_price')
            ->selectRaw('MAX(shops.emision) AS last_purchase_at')
            ->selectRaw('COUNT(DISTINCT shops.id) AS purchases_count')
            ->orderByRaw($this->orderByClause($filters['sort']));
    }

    /**
     * Ítems de compra de la empresa en contexto, ya filtrados. Solo líneas con valor
     * (> 0) para excluir ítems anulados o de relleno, igual que en Vinculación.
     *
     * @param  array{search: string, from: string, to: string, sort: string}  $filters
     */
    private function inventoryQuery(array $filters): Builder
    {
        return ShopItem::query()
            ->join('shops', 'shops.id', '=', 'shop_items.shop_id')
            ->join('products', 'products.id', '=', 'shop_items.product_id')
            ->join('contacts', 'contacts.id', '=', 'products.contact_id')
            ->where('shops.company_id', company()->id)
            ->where('shop_items.total', '>', 0)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']).'%';

                $query->where(function (Builder $q) use ($term): void {
                    $q->where('products.description', 'ilike', $term)
                        ->orWhere('products.code', 'ilike', $term)
                        ->orWhere('products.aux_code', 'ilike', $term)
                        ->orWhere('contacts.name', 'ilike', $term)
                        ->orWhere('contacts.identification', 'ilike', $term);
                });
            })
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('shops.emision', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('shops.emision', '<=', $filters['to']));
    }

    private function orderByClause(string $sort): string
    {
        return match ($sort) {
            'amount_desc' => 'SUM(shop_items.total) DESC',
            'quantity_desc' => 'SUM(shop_items.quantity) DESC',
            'price_desc' => '(array_agg(shop_items.unit_price ORDER BY shops.emision DESC, shop_items.id DESC))[1] DESC',
            'price_asc' => '(array_agg(shop_items.unit_price ORDER BY shops.emision DESC, shop_items.id DESC))[1] ASC',
            'description_asc' => 'products.description ASC',
            'contact_asc' => 'contacts.name ASC, products.description ASC',
            default => 'MAX(shops.emision) DESC, products.description ASC',
        };
    }

    private function currentCompanyName(): ?string
    {
        return Company::find(session('current_company_id'))?->name;
    }
}
