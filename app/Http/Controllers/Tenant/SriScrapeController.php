<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\ScrapeFromSriJob;
use App\Models\Tenant\SriScrapeJob;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SriScrapeController extends Controller
{
    private const SELECT_COLUMNS = [
        'id', 'type', 'year', 'month', 'day', 'mode', 'source',
        'voucher_types', 'status', 'progress', 'result', 'error_message',
        'created_at', 'completed_at',
    ];

    public function index(): Response
    {
        $company = company();

        $jobs = SriScrapeJob::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->select(self::SELECT_COLUMNS)
            ->paginate(15);

        try {
            $hasPassword = ! empty($company->pass_sri);
        } catch (DecryptException) {
            $hasPassword = false;
        }

        return Inertia::render('Tenant/SriScrape/Index', [
            'jobs' => $jobs,
            'hasPassword' => $hasPassword,
            'hasCaptchaKey' => true,
            'isRetentionAgent' => (bool) $company->retention_agent,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:compras,ventas'],
            'year' => ['required', 'integer', 'min:2022', 'max:'.now()->year],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'voucher_types' => ['required', 'array', 'min:1'],
            'voucher_types.*' => ['in:1,3,4,6'],
        ]);

        $company = company();

        try {
            $passSri = $company->pass_sri;
        } catch (DecryptException) {
            $passSri = null;
        }

        if (empty($passSri)) {
            return back()->with('error', 'Configure la clave SRI de la empresa primero.');
        }

        $existing = SriScrapeJob::where('company_id', $company->id)
            ->where('type', $validated['type'])
            ->whereIn('status', ['pending', 'running'])
            ->exists();

        if ($existing) {
            return back()->with('error', 'Ya existe una descarga en progreso para este tipo.');
        }

        $scrapeJob = SriScrapeJob::create([
            'company_id' => $company->id,
            'type' => $validated['type'],
            'year' => $validated['year'],
            'month' => $validated['month'],
            'day' => $validated['day'] ?? null,
            'mode' => 'txt_download',
            'source' => 'manual',
            'voucher_types' => $validated['voucher_types'],
            'status' => 'pending',
        ]);

        ScrapeFromSriJob::dispatch($scrapeJob->id, $company->id, tenancy()->tenant->getTenantKey());

        return back()->with('success', 'Descarga del SRI iniciada. Se actualizará el estado automáticamente.');
    }

    public function status(): JsonResponse
    {
        $company = company();

        $jobs = SriScrapeJob::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(self::SELECT_COLUMNS);

        return response()->json(['jobs' => $jobs]);
    }
}
