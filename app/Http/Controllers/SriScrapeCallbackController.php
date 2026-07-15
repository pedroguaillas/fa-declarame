<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessScrapeCallbackJob;
use App\Models\Tenant;
use App\Models\Tenant\SriScrapeJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SriScrapeCallbackController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $jobId = (int) $request->query('job');
        $tenantId = (string) $request->query('tenant');
        $token = (string) $request->query('token');

        $expected = hash_hmac('sha256', "{$jobId}:{$tenantId}", config('app.key'));

        if (! hash_equals($expected, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $body = $request->json()->all();

        if (($body['event'] ?? null) === 'error') {
            $tenant = Tenant::findOrFail($tenantId);
            tenancy()->initialize($tenant);

            try {
                $scrapeJob = SriScrapeJob::findOrFail($jobId);
                $scrapeJob->update([
                    'status' => 'failed',
                    'error_message' => $body['data']['message'] ?? 'Error desconocido',
                    'completed_at' => now(),
                ]);
            } finally {
                tenancy()->end();
            }

            return response()->json(['ok' => true]);
        }

        $payloadPath = "private/sri-scrape/callbacks/{$jobId}_".time().'.json';
        Storage::disk('local')->put($payloadPath, json_encode($body['data'] ?? [], JSON_UNESCAPED_UNICODE));

        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        try {
            $scrapeJob = SriScrapeJob::findOrFail($jobId);
            $companyId = $scrapeJob->company_id;
        } finally {
            tenancy()->end();
        }

        ProcessScrapeCallbackJob::dispatch($jobId, $companyId, $tenantId, $payloadPath);

        return response()->json(['ok' => true]);
    }
}
