<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Tenant\Company;
use App\Models\Tenant\SriScrapeJob;
use App\Services\SriScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SriScrapeCallbackController extends Controller
{
    public function handle(Request $request, SriScraperService $scraperService): JsonResponse
    {
        $jobId = (int) $request->query('job');
        $tenantId = (string) $request->query('tenant');
        $token = (string) $request->query('token');

        $expected = hash_hmac('sha256', "{$jobId}:{$tenantId}", config('app.key'));

        if (! hash_equals($expected, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        try {
            $scrapeJob = SriScrapeJob::findOrFail($jobId);
            $company = Company::findOrFail($scrapeJob->company_id);

            $body = $request->json()->all();

            if (($body['event'] ?? null) === 'error') {
                $scrapeJob->update([
                    'status' => 'failed',
                    'error_message' => $body['data']['message'] ?? 'Error desconocido',
                    'completed_at' => now(),
                ]);

                return response()->json(['ok' => true]);
            }

            $scraperService->processResult($body['data'] ?? [], $scrapeJob, $company);

            return response()->json(['ok' => true]);
        } finally {
            tenancy()->end();
        }
    }
}
