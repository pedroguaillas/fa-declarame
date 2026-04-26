<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreOrderRequest;
use App\Http\Requests\Tenant\UpdateOrderRequest;
use App\Models\Tenant\Order;
use App\Models\Tenant\Retention;
use App\Models\Tenant\VoucherType;
use App\Services\OrderImportService;
use App\Services\OrderRetentionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        $orders = Order::with(['contact:id,name', 'retentionItems.retention'])
            ->join('voucher_types AS vt', 'vt.id', 'voucher_type_id')
            ->orderByDesc('emision')
            ->paginate(25, [
                'orders.id',
                'contact_id',
                'serie',
                'emision',
                'autorization',
                'initial',
                'sub_total',
                'no_iva',
                'base0',
                'base5',
                'base12',
                'base15',
                'iva5',
                'iva12',
                'iva15',
                'discount',
                'ice',
                'total',
                'state',
                'serie_retention',
                'date_retention',
                'state_retention',
                'autorization_retention',
            ]);

        return Inertia::render('Tenant/Orders/Index', [
            'orders' => $orders,
            'retentions' => Retention::orderBy('code')->get(['id', 'code', 'type', 'description', 'percentage']),
        ]);
    }

    public function create(): Response
    {
        $voucherTypes = VoucherType::whereIn('code', ['01', '02', '04', '05'])->get();

        return Inertia::render('Tenant/Orders/Create', [
            'voucherTypes' => $voucherTypes,
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        Order::create(array_merge($request->validated(), [
            'company_id' => session('current_company_id'),
        ]));

        return redirect()->route('tenant.orders.index')
            ->with('success', 'Venta registrada correctamente.');
    }

    public function edit(Order $order): Response
    {
        $voucherTypes = VoucherType::whereIn('code', ['01', '02', '04'])->get();

        return Inertia::render('Tenant/Orders/Edit', [
            'order' => $order->load('contact'),
            'voucherTypes' => $voucherTypes,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update($request->validated());

        return redirect()->route('tenant.orders.index')
            ->with('success', 'Venta actualizada correctamente.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()->route('tenant.orders.index')
            ->with('success', 'Venta eliminada correctamente.');
    }

    public function import(Request $request, OrderImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
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

                $result = $service->import($content, $company->id, $company->ruc);
                $imported += $result['imported'];
                $skipped += $result['skipped'];
                $errors += $result['errors'];
            }

            $zip->close();
        } else {
            $content = file_get_contents($request->file('file')->getRealPath());

            ['imported' => $imported, 'skipped' => $skipped] = $service->import($content, $company->id, $company->ruc);
        }

        return redirect()->route('tenant.orders.index')
            ->with('success', "Importación completada: {$imported} ventas importadas, {$skipped} omitidas.");
    }

    public function importRetentions(Request $request, OrderRetentionImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $company = company();
        $content = file_get_contents($request->file('file')->getRealPath());

        ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors] = $service->import($content, $company->ruc);

        return redirect()->route('tenant.orders.index')
            ->with('success', "Retenciones importadas: {$imported} procesadas, {$skipped} omitidas, {$errors} errores.");
    }

    public function storeRetention(Request $request, Order $order): RedirectResponse
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

        $order->update([
            'serie_retention' => $validated['serie_retention'],
            'date_retention' => $validated['date_retention'],
            'autorization_retention' => $validated['autorization_retention'],
            'state_retention' => 'AUTORIZADO',
        ]);

        $order->retentionItems()->delete();

        $order->retentionItems()->createMany($validated['items']);

        return redirect()->route('tenant.orders.index')
            ->with('success', 'Retención registrada correctamente.');
    }
}
