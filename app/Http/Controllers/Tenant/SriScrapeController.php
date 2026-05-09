<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\ScrapeFromSriJob;
use App\Models\Tenant\SriScrapeJob;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SriScrapeController extends Controller
{
    public function index(): Response
    {
        $company = company();

        $jobs = SriScrapeJob::where('company_id', $company->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->get(['id', 'type', 'year', 'month', 'mode', 'status', 'progress', 'result', 'error_message', 'created_at', 'completed_at']);

        return Inertia::render('Tenant/SriScrape/Index', [
            'jobs' => $jobs,
            'hasPassword' => ! empty($company->pass_sri),
            'hasCaptchaKey' => true, // No longer required — stealth bypasses captcha
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:compras,ventas'],
            'year' => ['required', 'integer', 'min:2022', 'max:'.now()->year],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $company = company();

        if (empty($company->pass_sri)) {
            return back()->with('error', 'Configure la clave SRI de la empresa primero.');
        }

        // Prevent duplicate running jobs
        $existing = SriScrapeJob::where('company_id', $company->id)
            ->where('type', $validated['type'])
            ->whereIn('status', ['pending', 'running'])
            ->exists();

        if ($existing) {
            return back()->with('error', 'Ya existe una descarga en progreso para este tipo.');
        }

        // Determine mode based on date
        $requestedDate = Carbon::create($validated['year'], $validated['month'], 1);
        $previousMonth = now()->subMonth()->startOfMonth();
        $mode = $requestedDate->gte($previousMonth) ? 'txt_download' : 'table_scrape';

        $scrapeJob = SriScrapeJob::create([
            'company_id' => $company->id,
            'type' => $validated['type'],
            'year' => $validated['year'],
            'month' => $validated['month'],
            'mode' => $mode,
            'status' => 'pending',
        ]);

        ScrapeFromSriJob::dispatch($scrapeJob->id, $company->id);

        return back()->with('success', 'Descarga del SRI iniciada. Se actualizará el estado automáticamente.');
    }

    public function status(): JsonResponse
    {
        $company = company();

        $jobs = SriScrapeJob::where('company_id', $company->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'type', 'year', 'month', 'mode', 'status', 'progress', 'result', 'error_message', 'created_at', 'completed_at']);

        return response()->json(['jobs' => $jobs]);
    }
}
