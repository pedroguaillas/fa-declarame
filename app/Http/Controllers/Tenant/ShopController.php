<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\ShopsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreShopRequest;
use App\Http\Requests\Tenant\UpdateShopRequest;
use App\Models\Tenant\Shop;
use App\Models\Tenant\TaxSupport;
use App\Models\Tenant\VoucherType;
use App\Services\ShopImportService;
use App\Services\ShopRetentionImportService;
use Constants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'period', 'retention', 'voucher_type']);

        if (empty($filters['period'])) {
            $lastEmision = Shop::max('emision');
            $filters['period'] = $lastEmision
                ? substr($lastEmision, 0, 7)
                : now()->format('Y-m');
        }

        $shops = $this->filteredShopsQuery($filters)
            ->selectRaw('shops.id, shops.account_id, contact_id, serie, emision, vt.code AS voucher_type_code, total, shops.state, serie_retention, SUM(value) AS retention_amount')
            ->with(['contact:id,name'])
            ->leftJoin('shop_retention_items', 'shop_id', 'shops.id')
            ->orderByDesc('emision')
            ->groupBy('shops.id', 'shops.account_id', 'contact_id', 'serie', 'emision', 'vt.code', 'total', 'shops.state', 'serie_retention')
            ->paginate(25)
            ->withQueryString();

        $company = company();
        $isRetentionAgent = (bool) $company?->retention_agent;
        $isSpecialAgent = (bool) $company?->special_contribution;
        $containLC = $shops->contains('code', Constants::LIQUIDACION_COMPRA);
        $isActiveRetention = $isRetentionAgent || $isSpecialAgent || $containLC;

        return Inertia::render('Tenant/Shops/Index', [
            'shops' => $shops,
            'isActiveRetention' => $isActiveRetention,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Shops/Create', [
            'voucherTypes' => VoucherType::whereIn('code', ['01', '02', '03', '04', '05'])->get(),
        ]);
    }

    public function store(StoreShopRequest $request): RedirectResponse
    {
        $taxSupportId = null;
        $voucherType = VoucherType::find($request->voucher_type_id);

        if ($voucherType->code === Constants::NOTA_VENTA) {
            $taxSupportId = TaxSupport::where('code', Constants::NOTA_VENTA)->value('id');
        } else {
            $taxSupportId = TaxSupport::where('code', Constants::FACTURA)->value('id');
        }

        Shop::create(array_merge($request->validated(), [
            'company_id' => session('current_company_id'),
            'tax_support_id' => $taxSupportId,
        ]));

        return redirect()->route('tenant.shops.index')
            ->with('success', 'Compra registrada correctamente.');
    }

    public function show(Shop $shop): JsonResponse
    {
        return response()->json($shop->load(['contact:id,name', 'account:id,code,name', 'items.product', 'retentionItems.retention']));
    }

    public function edit(Shop $shop): Response
    {
        $shopData = $shop->load('contact')->toArray();

        $floatFields = ['sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'aditional_discount', 'discount', 'ice', 'total'];

        foreach ($floatFields as $field) {
            if (isset($shopData[$field])) {
                $shopData[$field] = (float) $shopData[$field];
            }
        }

        return Inertia::render('Tenant/Shops/Edit', [
            'shop' => $shopData,
            'voucherTypes' => VoucherType::whereIn('code', ['01', '02', '03', '04', '05'])->get(),
        ]);
    }

    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update($request->validated());

        return redirect()->route('tenant.shops.index')
            ->with('success', 'Compra actualizada correctamente.');
    }

    public function destroy(Shop $shop): RedirectResponse
    {
        $shop->delete();

        return redirect()->route('tenant.shops.index')
            ->with('success', 'Compra eliminada correctamente.');
    }

    /** @param array<string, string> $filters */
    private function filteredShopsQuery(array $filters): Builder
    {
        return Shop::join('voucher_types AS vt', 'vt.id', 'shops.voucher_type_id')
            ->when($filters['search'] ?? null, function ($q, $s) {
                $hasLetters = (bool) preg_match('/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/u', $s);
                $isOnlyDigits = ctype_digit($s);
                $len = strlen($s);

                if ($hasLetters) {
                    $q->join('contacts AS sc', 'sc.id', '=', 'shops.contact_id')
                        ->where('sc.name', 'ilike', "%{$s}%");
                } elseif ($isOnlyDigits && $len === 49) {
                    $q->where('shops.autorization', $s);
                } elseif ($isOnlyDigits && $len >= 5) {
                    $q->where(function ($q) use ($s) {
                        $q->where('shops.serie', 'ilike', "%{$s}%")
                            ->orWhere('shops.autorization', 'ilike', "%{$s}%");
                    });
                } else {
                    $q->where('shops.serie', 'ilike', "%{$s}%");
                }
            })
            ->when($filters['period'] ?? null, function ($q, $p) {
                $q->whereYear('emision', substr($p, 0, 4))
                    ->whereMonth('emision', substr($p, 5, 2));
            })
            ->when($filters['retention'] ?? null, fn ($q, $r) => $r === 'with'
                ? $q->whereNotNull('serie_retention')
                : $q->whereNull('serie_retention')
            )
            ->when($filters['voucher_type'] ?? null, fn ($q, $v) => $q->where('vt.code', $v));
    }

    public function import(Request $request, ShopImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $company = company();
        $content = file_get_contents($request->file('file')->getRealPath());

        ['imported' => $imported, 'skipped' => $skipped] = $service->import($content, $company->id, $company->ruc);

        return redirect()->route('tenant.shops.index')
            ->with($skipped > 0 ? 'error' : 'success', "Importación completada: {$imported} compras importadas, {$skipped} omitidas.");
    }

    public function importRetentions(Request $request, ShopRetentionImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:txt,zip'],
        ]);

        $company = company();
        $uploaded = $request->file('file');

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        $failedKeys = [];

        if ($uploaded->getClientOriginalExtension() === 'zip') {
            $zip = new \ZipArchive;

            if ($zip->open($uploaded->getRealPath()) !== true) {
                return redirect()->route('tenant.shops.index')
                    ->with('error', 'No se pudo abrir el archivo ZIP.');
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'txt') {
                    continue;
                }

                $content = $zip->getFromIndex($i);

                if ($content === false) {
                    continue;
                }

                $result = $service->import($content, $company->ruc);
                $imported += $result['imported'];
                $skipped += $result['skipped'];
                $errors += $result['errors'];
                $failedKeys = array_merge($failedKeys, $result['failedKeys']);
            }

            $zip->close();
        } else {
            $content = file_get_contents($uploaded->getRealPath());
            $result = $service->import($content, $company->ruc);
            $imported = $result['imported'];
            $skipped = $result['skipped'];
            $errors = $result['errors'];
            $failedKeys = $result['failedKeys'];
        }

        $redirect = redirect()->route('tenant.shops.index')
            ->with($skipped > 0 || $errors > 0 ? 'error' : 'success', "Retenciones importadas: {$imported} procesadas, {$skipped} omitidas, {$errors} errores.");

        if (! empty($failedKeys)) {
            $redirect = $redirect->with('failed_keys', $failedKeys);
        }

        return $redirect;
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['search', 'period', 'retention', 'voucher_type']);
        $allColumns = array_keys(ShopsExport::$availableColumns);
        $columns = $request->has('columns')
            ? array_intersect((array) $request->get('columns'), $allColumns)
            : $allColumns;

        return Excel::download(new ShopsExport($this->filteredShopsQuery($filters), array_values($columns)), 'compras.xlsx');
    }

    public function updateAccount(Request $request, Shop $shop): RedirectResponse
    {
        $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $shop->update(['account_id' => $request->account_id]);

        return redirect()->route('tenant.shops.index')
            ->with('success', 'Cuenta contable asignada correctamente.');
    }

    public function storeRetention(Request $request, Shop $shop): RedirectResponse
    {
        $validated = $request->validate([
            'serie_retention' => ['required', 'string', 'max:17'],
            'date_retention' => ['required', 'date'],
            'autorization_retention' => ['required', 'string', 'max:49'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.retention_id' => ['required', 'integer', 'exists:retentions,id'],
            'items.*.base' => ['required', 'numeric', 'min:0'],
            'items.*.percentage' => ['required', 'numeric', 'min:0'],
            'items.*.value' => ['required', 'numeric', 'min:0'],
        ]);

        $shop->update([
            'serie_retention' => $validated['serie_retention'],
            'date_retention' => $validated['date_retention'],
            'autorization_retention' => $validated['autorization_retention'],
            'state_retention' => 'AUTORIZADO',
        ]);

        $shop->retentionItems()->delete();

        $shop->retentionItems()->createMany($validated['items']);

        return redirect()->route('tenant.shops.index')
            ->with('success', 'Retención registrada correctamente.');
    }
}
