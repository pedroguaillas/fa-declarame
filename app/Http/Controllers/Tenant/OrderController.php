<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\OrdersExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreOrderRequest;
use App\Http\Requests\Tenant\UpdateOrderRequest;
use App\Models\Tenant\Order;
use App\Models\Tenant\VoucherType;
use App\Services\OrderImportService;
use App\Services\OrderRetentionImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'period', 'voucher_type']);

        if (empty($filters['period'])) {
            $lastEmision = Order::max('emision');
            $filters['period'] = $lastEmision
                ? substr($lastEmision, 0, 7)
                : now()->format('Y-m');
        }

        $orders = Order::selectRaw('orders.id, contact_id, serie, emision, autorization, total, state, serie_retention, state_retention, vt.code')
            ->with(['contact:id,name'])
            ->join('voucher_types AS vt', 'vt.id', 'voucher_type_id')
            ->when($filters['search'] ?? null, function ($q, $s) {
                $hasLetters = (bool) preg_match('/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/u', $s);
                $isOnlyDigits = ctype_digit($s);
                $len = strlen($s);

                if ($hasLetters) {
                    $q->join('contacts AS sc', 'sc.id', '=', 'orders.contact_id')
                        ->where('sc.name', 'ilike', "%{$s}%");
                } elseif ($isOnlyDigits && $len === 49) {
                    $q->where('orders.autorization', $s);
                } elseif ($isOnlyDigits && $len >= 5) {
                    $q->where(function ($q) use ($s) {
                        $q->where('orders.serie', 'ilike', "%{$s}%")
                            ->orWhere('orders.autorization', 'ilike', "%{$s}%");
                    });
                } else {
                    $q->where('orders.serie', 'ilike', "%{$s}%");
                }
            })
            ->when($filters['period'] ?? null, function ($q, $p) {
                $q->whereYear('emision', substr($p, 0, 4))
                    ->whereMonth('emision', substr($p, 5, 2));
            })
            ->when($filters['voucher_type'] ?? null, fn ($q, $v) => $q->where('vt.code', $v))
            ->orderByDesc('emision')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Tenant/Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $voucherTypes = VoucherType::whereIn('code', ['01', '04', '05'])->get();

        return Inertia::render('Tenant/Orders/Create', [
            'voucherTypes' => $voucherTypes,
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        Order::create(array_merge(
            $request->validated(),
            [
                'company_id' => session('current_company_id'),
            ]
        ));

        return redirect()->route('tenant.orders.index')
            ->with('success', 'Venta registrada correctamente.');
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['contact:id,name', 'retentionItems.retention']));
    }

    public function edit(Order $order): Response
    {
        $voucherTypes = VoucherType::whereIn('code', ['01', '02', '04'])->get();

        $orderData = $order->load('contact')->toArray();

        $floatFields = ['sub_total', 'no_iva', 'exempt', 'base0', 'base5', 'base8', 'base12', 'base15', 'iva5', 'iva8', 'iva12', 'iva15', 'aditional_discount', 'discount', 'ice', 'total'];

        foreach ($floatFields as $field) {
            if (isset($orderData[$field])) {
                $orderData[$field] = (float) $orderData[$field];
            }
        }

        return Inertia::render('Tenant/Orders/Edit', [
            'order' => $orderData,
            'voucherTypes' => $voucherTypes,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update($request->validated());

        return redirect()
            ->route('tenant.orders.index')
            ->with('success', 'Venta actualizada correctamente.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()
            ->route('tenant.orders.index')
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

                return redirect()->route('tenant.orders.index')->with('error', 'No se pudo abrir el archivo ZIP.');
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

        return redirect()->route('tenant.orders.index')->with($skipped > 0 ? 'error' : 'success', "Importación completada: {$imported} ventas importadas, {$skipped} omitidas.");
    }

    public function importRetentions(Request $request, OrderRetentionImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $company = company();

        $content = file_get_contents(
            $request->file('file')->getRealPath()
        );

        ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'failedKeys' => $failedKeys] = $service->import($content, $company->ruc);

        $redirect = redirect()->route('tenant.orders.index')
            ->with($skipped > 0 || $errors > 0 ? 'error' : 'success', "Retenciones importadas: {$imported} procesadas, {$skipped} omitidas, {$errors} errores.");

        if (! empty($failedKeys)) {
            $redirect = $redirect->with('failed_keys', $failedKeys);
        }

        return $redirect;
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->only(['search', 'period', 'voucher_type']);
        $allColumns = array_keys(OrdersExport::$availableColumns);
        $columns = $request->has('columns')
            ? array_intersect((array) $request->get('columns'), $allColumns)
            : $allColumns;

        return Excel::download(new OrdersExport($this->filteredOrdersQuery($filters), array_values($columns)), 'ventas.xlsx');
    }

    /** @param array<string, string> $filters */
    private function filteredOrdersQuery(array $filters): Builder
    {
        return Order::join('voucher_types AS vt', 'vt.id', 'voucher_type_id')
            ->when($filters['search'] ?? null, function ($q, $s) {
                $hasLetters = (bool) preg_match('/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/u', $s);
                $isOnlyDigits = ctype_digit($s);
                $len = strlen($s);

                if ($hasLetters) {
                    $q->join('contacts AS sc', 'sc.id', '=', 'orders.contact_id')
                        ->where('sc.name', 'ilike', "%{$s}%");
                } elseif ($isOnlyDigits && $len === 49) {
                    $q->where('orders.autorization', $s);
                } elseif ($isOnlyDigits && $len >= 5) {
                    $q->where(function ($q) use ($s) {
                        $q->where('orders.serie', 'ilike', "%{$s}%")
                            ->orWhere('orders.autorization', 'ilike', "%{$s}%");
                    });
                } else {
                    $q->where('orders.serie', 'ilike', "%{$s}%");
                }
            })
            ->when($filters['period'] ?? null, function ($q, $p) {
                $q->whereYear('emision', substr($p, 0, 4))
                    ->whereMonth('emision', substr($p, 5, 2));
            })
            ->when($filters['voucher_type'] ?? null, fn ($q, $v) => $q->where('vt.code', $v));
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

        return redirect()
            ->route('tenant.orders.index')
            ->with(
                'success',
                'Retención registrada correctamente.'
            );
    }
}
