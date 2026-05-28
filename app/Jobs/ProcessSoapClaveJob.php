<?php

namespace App\Jobs;

use App\Models\Tenant\Company;
use App\Models\Tenant\Order;
use App\Models\Tenant\Scopes\CompanyScope;
use App\Models\Tenant\Shop;
use App\Models\Tenant\SriScrapeJob;
use App\Services\OrderImportService;
use App\Services\OrderRetentionImportService;
use App\Services\ShopImportService;
use App\Services\ShopRetentionImportService;
use App\Services\SriSoapService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessSoapClaveJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> Seconds to wait before each retry */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly string $claveAcceso,
        public readonly int $companyId,
        public readonly string $scrapeType, // 'compras' | 'ventas'
        public readonly ?int $scrapeJobId = null,
    ) {}

    public function handle(
        SriSoapService $sriSoapService,
        OrderRetentionImportService $orderRetentionImportService,
        ShopRetentionImportService $shopRetentionImportService,
        ShopImportService $shopImportService,
        OrderImportService $orderImportService,
    ): void {
        $process = function () use (
            $sriSoapService,
            $orderRetentionImportService,
            $shopRetentionImportService,
            $shopImportService,
            $orderImportService,
        ) {
            $this->processClave(
                $sriSoapService,
                $orderRetentionImportService,
                $shopRetentionImportService,
                $shopImportService,
                $orderImportService,
            );
        };

        try {
            Redis::throttle('sri-soap')
                ->allow(2)->every(1)
                ->then($process, fn () => $this->release(5));
        } catch (\Throwable) {
            // Redis not available — process without throttling
            $process();
        }
    }

    private function processClave(
        SriSoapService $sriSoapService,
        OrderRetentionImportService $orderRetentionImportService,
        ShopRetentionImportService $shopRetentionImportService,
        ShopImportService $shopImportService,
        OrderImportService $orderImportService,
    ): void {
        $voucherTypeCode = substr($this->claveAcceso, 8, 2);

        // Skip duplicates without hitting SOAP (retention handled internally by processRetention)
        if ($voucherTypeCode !== '07' && $this->alreadyImported()) {
            $this->incrementScrapeJobResult('skipped');

            return;
        }

        $company = Company::findOrFail($this->companyId);
        $autorizacion = $sriSoapService->authorize($this->claveAcceso);

        if ($autorizacion === null) {
            $attempt = $this->attempts();
            if ($attempt < $this->tries) {
                $delay = $this->backoff[min($attempt - 1, count($this->backoff) - 1)] ?? 10;
                $this->release($delay);

                return;
            }

            Log::warning('ProcessSoapClaveJob: SRI SOAP no respondió después de todos los intentos', [
                'clave' => $this->claveAcceso,
                'company_id' => $this->companyId,
                'scrape_type' => $this->scrapeType,
            ]);

            $this->incrementScrapeJobResult('errors');

            return;
        }

        $imported = 0;

        if ($voucherTypeCode === '07') {
            $service = $this->scrapeType === 'compras'
                ? $orderRetentionImportService
                : $shopRetentionImportService;
            $result = $service->processRetention($autorizacion, $company->ruc);
            $imported = $result['imported'] ?? 0;
        } elseif ($this->scrapeType === 'compras') {
            $result = $shopImportService->processFromAutorizacion($autorizacion, $this->claveAcceso, $company->id, $company->ruc);
            $imported = $result['imported'] ?? 0;
        } else {
            $result = $orderImportService->processFromAutorizacion($autorizacion, $this->claveAcceso, $company->id, $company->ruc);
            $imported = $result['imported'] ?? 0;
        }

        if ($imported > 0) {
            $this->incrementScrapeJobResult('imported', $imported);
        } elseif ($imported === 0) {
            $this->incrementScrapeJobResult('skipped');
        }
    }

    private function alreadyImported(): bool
    {
        if ($this->scrapeType === 'compras') {
            return Shop::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $this->companyId)
                ->where('autorization', $this->claveAcceso)
                ->exists();
        }

        return Order::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $this->companyId)
            ->where('autorization', $this->claveAcceso)
            ->exists();
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('ProcessSoapClaveJob: clave falló después de todos los reintentos', [
            'clave' => $this->claveAcceso,
            'company_id' => $this->companyId,
            'scrape_type' => $this->scrapeType,
            'error' => $exception->getMessage(),
        ]);

        $this->incrementScrapeJobResult('errors');
    }

    /**
     * Atomically increment a counter key inside SriScrapeJob.result JSON column.
     */
    private function incrementScrapeJobResult(string $key, int $amount = 1): void
    {
        if ($this->scrapeJobId === null || $amount === 0) {
            return;
        }

        DB::transaction(function () use ($key, $amount) {
            $record = SriScrapeJob::lockForUpdate()->find($this->scrapeJobId);

            if ($record) {
                $result = $record->result ?? [];
                $result[$key] = ($result[$key] ?? 0) + $amount;
                $record->update(['result' => $result]);
            }
        });
    }
}
