<?php

namespace App\Jobs;

use App\Models\Tenant\Company;
use App\Models\Tenant\SriScrapeJob;
use App\Services\SriScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessScrapeCallbackJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly int $scrapeJobId,
        public readonly int $companyId,
        public readonly string $tenantId,
        public readonly string $payloadPath,
    ) {}

    public function handle(SriScraperService $scraperService): void
    {
        $raw = Storage::disk('local')->get($this->payloadPath);

        if ($raw === null) {
            Log::error('ProcessScrapeCallbackJob: payload file not found', [
                'path' => $this->payloadPath,
            ]);

            return;
        }

        $data = json_decode($raw, true) ?? [];

        Storage::disk('local')->delete($this->payloadPath);

        tenancy()->initialize($this->tenantId);

        try {
            $scrapeJob = SriScrapeJob::findOrFail($this->scrapeJobId);
            $company = Company::findOrFail($this->companyId);

            $scraperService->processResult($data, $scrapeJob, $company);
        } finally {
            tenancy()->end();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $scrapeJob = SriScrapeJob::find($this->scrapeJobId);
            $scrapeJob?->update([
                'status' => 'failed',
                'error_message' => $exception?->getMessage() ?? 'Error procesando callback',
                'completed_at' => now(),
            ]);
        } finally {
            tenancy()->end();
        }

        Storage::disk('local')->delete($this->payloadPath);
    }
}
