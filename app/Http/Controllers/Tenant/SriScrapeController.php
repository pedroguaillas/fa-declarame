<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\ScrapeFromSriJob;
use App\Models\Tenant\SriScrapeJob;
use App\Services\SriScraperService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SriScrapeController extends Controller
{
    /** Timeout del job semestral: 6 meses en una sola sesión puede tardar hasta ~30 min. */
    private const SEMESTER_JOB_TIMEOUT = 3600;

    private const SELECT_COLUMNS = [
        'id', 'type', 'year', 'month', 'end_month', 'day', 'mode', 'source',
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
            'typeDeclaration' => $company->type_declaration,
            'agentInstallUrl' => rtrim(config('app.url'), '/').'/agent',
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
            'full_semester' => ['nullable', 'boolean'],
        ]);

        if (in_array('6', $validated['voucher_types']) && count($validated['voucher_types']) > 1) {
            return back()->with('error', 'Las retenciones no pueden combinarse con otros tipos de comprobante.');
        }

        $fullSemester = (bool) ($validated['full_semester'] ?? false);

        if ($fullSemester && $validated['type'] !== 'compras') {
            return back()->with('error', 'La descarga semestral solo está disponible para comprobantes recibidos.');
        }

        [$month, $endMonth] = $fullSemester
            ? self::resolveSemesterRange((int) $validated['year'], (int) $validated['month'])
            : [(int) $validated['month'], null];

        $day = $fullSemester ? null : ($validated['day'] ?? null);

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

        $previousJobs = SriScrapeJob::forPeriod(
            $company->id,
            $validated['type'],
            $validated['year'],
            $month,
            $day,
            $endMonth,
        )->whereIn('status', ['completed', 'failed'])->get(['status', 'result', 'voucher_types']);

        if ($blockReason = SriScrapeJob::blockReason($previousJobs, $validated['voucher_types'])) {
            return back()->with('error', $blockReason);
        }

        $scrapeJob = SriScrapeJob::create([
            'company_id' => $company->id,
            'type' => $validated['type'],
            'year' => $validated['year'],
            'month' => $month,
            'end_month' => $endMonth,
            'day' => $day,
            'mode' => 'txt_download',
            'source' => 'manual',
            'voucher_types' => $validated['voucher_types'],
            'status' => 'pending',
        ]);

        ScrapeFromSriJob::dispatch(
            $scrapeJob->id,
            $company->id,
            tenancy()->tenant->getTenantKey(),
            $fullSemester ? self::SEMESTER_JOB_TIMEOUT : ScrapeFromSriJob::DEFAULT_TIMEOUT,
        );

        return back()->with('success', 'Descarga del SRI iniciada. Se actualizará el estado automáticamente.');
    }

    /**
     * Deriva el rango del semestre desde el mes elegido (1-6 → S1, 7-12 → S2),
     * capado al mes actual cuando es el año en curso.
     *
     * @return array{int, int}
     */
    public static function resolveSemesterRange(int $year, int $month): array
    {
        $startMonth = $month <= 6 ? 1 : 7;
        $endMonth = $month <= 6 ? 6 : 12;

        if ($year === now()->year) {
            $endMonth = min($endMonth, now()->month);
        }

        return [$startMonth, $endMonth];
    }

    /**
     * Create a SriScrapeJob for the desktop agent and return the signed config payload.
     * The frontend passes this config to localhost:8765/scrape; the agent POSTs results
     * back to /scrape-callback using the signed callbackUrl included in the config.
     */
    public function agentDispatch(Request $request, SriScraperService $scraperService): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:compras,ventas'],
            'year' => ['required', 'integer', 'min:2022', 'max:'.now()->year],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'voucher_types' => ['required', 'array', 'min:1'],
            'voucher_types.*' => ['in:1,3,4,6'],
            'full_semester' => ['nullable', 'boolean'],
        ]);

        if (in_array('6', $validated['voucher_types']) && count($validated['voucher_types']) > 1) {
            return response()->json(['error' => 'Las retenciones no pueden combinarse con otros tipos de comprobante.'], 422);
        }

        $fullSemester = (bool) ($validated['full_semester'] ?? false);

        if ($fullSemester && $validated['type'] !== 'compras') {
            return response()->json(['error' => 'La descarga semestral solo está disponible para comprobantes recibidos.'], 422);
        }

        [$month, $endMonth] = $fullSemester
            ? self::resolveSemesterRange((int) $validated['year'], (int) $validated['month'])
            : [(int) $validated['month'], null];

        $day = $fullSemester ? null : ($validated['day'] ?? null);

        $company = company();

        try {
            $passSri = $company->pass_sri;
        } catch (DecryptException) {
            $passSri = null;
        }

        if (empty($passSri)) {
            return response()->json(['error' => 'Configure la clave SRI de la empresa primero.'], 422);
        }

        $existing = SriScrapeJob::where('company_id', $company->id)
            ->where('type', $validated['type'])
            ->whereIn('status', ['pending', 'running'])
            ->exists();

        if ($existing) {
            return response()->json(['error' => 'Ya existe una descarga en progreso para este tipo.'], 409);
        }

        $previousJobs = SriScrapeJob::forPeriod(
            $company->id,
            $validated['type'],
            $validated['year'],
            $month,
            $day,
            $endMonth,
        )->whereIn('status', ['completed', 'failed'])->get(['status', 'result', 'voucher_types']);

        if ($blockReason = SriScrapeJob::blockReason($previousJobs, $validated['voucher_types'])) {
            return response()->json(['error' => $blockReason], 422);
        }

        $scrapeJob = SriScrapeJob::create([
            'company_id' => $company->id,
            'type' => $validated['type'],
            'year' => $validated['year'],
            'month' => $month,
            'end_month' => $endMonth,
            'day' => $day,
            'mode' => 'txt_download',
            'source' => 'agent',
            'voucher_types' => $validated['voucher_types'],
            'status' => 'pending',
        ]);

        $tenantId = tenancy()->tenant->getTenantKey();

        $config = $scraperService->buildAgentConfig($scrapeJob, $company, $tenantId);

        return response()->json([
            'jobId' => $scrapeJob->id,
            'config' => $config,
        ]);
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

    /**
     * Marca un job como "running" cuando el agente local lo acepta. El agente
     * solo hace callback al final, así que sin esto el job quedaría en "pending"
     * durante todo el procesamiento.
     */
    public function markRunning(SriScrapeJob $job): JsonResponse
    {
        $company = company();

        if ($job->company_id !== $company->id) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if ($job->status === 'pending') {
            $job->update(['status' => 'running']);
        }

        return response()->json(['ok' => true]);
    }
}
