<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreShopRequest;
use App\Http\Requests\Tenant\UpdateShopRequest;
use App\Models\Tenant\Account;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Retention;
use App\Models\Tenant\TaxSupport;
use App\Models\Tenant\Shop;
use App\Models\Tenant\VoucherType;
use App\Services\ShopImportService;
use App\Services\ShopRetentionImportService;
use Constants;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(): Response
    {
        $shops = Shop::selectRaw('shops.id, account_id, contact_id, serie, emision, autorization, initial, vt.code, sub_total, no_iva, base0, base5, base12, base15, iva5, iva12, iva15, discount, ice, total, state, serie_retention, date_retention, state_retention, autorization_retention')
            ->with(['contact:id,name', 'retentionItems.retention', 'account:id,code,name'])
            ->join('voucher_types AS vt', 'vt.id', 'voucher_type_id')
            ->orderByDesc('emision')
            ->paginate(25);

        $company = company();
        $isRetentionAgent = (bool) $company?->retention_agent;
        $isSpecialAgent = (bool) $company?->special_contribution;
        $containLC = $shops->contains('code', Constants::LIQUIDACION_COMPRA);
        $isActiveRetention = false;
        $retentions = [];

        if ($isRetentionAgent || $isSpecialAgent || $containLC) {
            $isActiveRetention = true;
            $retentions = Retention::orderBy('code')->get(['id', 'code', 'type', 'description', 'percentage']);
        }

        return Inertia::render('Tenant/Shops/Index', [
            'shops' => $shops,
            'retentions' => $retentions,
            'accounts' => $this->expenseAccounts(),
            'isActiveRetention' => $isActiveRetention,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tenant/Shops/Create', [
            'voucherTypes' => VoucherType::whereIn('code', ['01', '02', '03', '04', '05'])->get(),
            'accounts' => $this->expenseAccounts(),
            'identificationTypes' => $this->identificationTypes()
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

    public function edit(Shop $shop): Response
    {
        return Inertia::render('Tenant/Shops/Edit', [
            'shop' => $shop->load('contact'),
            'voucherTypes' => VoucherType::whereIn('code', ['01', '02', '03', '04', '05'])->get(),
            'accounts' => $this->expenseAccounts(),
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

    /** @return Collection<int, Account> */
    private function expenseAccounts(): Collection
    {
        return Account::where('code', 'like', '5%')
            ->where('is_detail', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    private function identificationTypes(): Collection
    {
        return IdentificationType::where('description', '!=', 'CONSUMIDOR FINAL')->get();
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
            ->with('success', "Importación completada: {$imported} compras importadas, {$skipped} omitidas.");
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
            }

            $zip->close();
        } else {
            $content = file_get_contents($uploaded->getRealPath());
            ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors] = $service->import($content, $company->ruc);
        }

        return redirect()->route('tenant.shops.index')
            ->with('success', "Retenciones importadas: {$imported} procesadas, {$skipped} omitidas, {$errors} errores.");
    }

    public function updateAccount(Request $request, Shop $shop): RedirectResponse
    {
        $request->validate([
            'acount_id' => ['nullable', 'integer', 'exists:acounts,id'],
        ]);

        $shop->update(['acount_id' => $request->acount_id]);

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
