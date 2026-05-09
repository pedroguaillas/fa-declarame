<?php

namespace App\Jobs;

use App\Models\Tenant\Company;
use App\Models\Tenant\SriScrapeJob;
use App\Services\SriScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScrapeFromSriJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly int $scrapeJobId,
        public readonly int $companyId,
    ) {}

    public function handle(SriScraperService $scraperService): void
    {
        $scrapeJob = SriScrapeJob::findOrFail($this->scrapeJobId);
        $company = Company::findOrFail($this->companyId);

        $scraperService->execute($scrapeJob, $company);
    }

    public function failed(?\Throwable $exception): void
    {
        $scrapeJob = SriScrapeJob::find($this->scrapeJobId);

        $scrapeJob?->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Error desconocido',
            'completed_at' => now(),
        ]);
    }
}
