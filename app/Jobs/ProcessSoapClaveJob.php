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
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;

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

        $processStarted = false;

        try {
            // block() espera turno en vez de fallar al instante: con la cola `sync`
            // release() no reencola nada y el comprobante se perdía en silencio.
            Redis::throttle('sri-soap')
                ->allow(2)->every(1)
                ->block(30)
                ->then(function () use ($process, &$processStarted) {
                    $processStarted = true;
                    $process();
                }, fn () => $this->retryLaterOrFail('throttle'));
        } catch (\Throwable $exception) {
            if ($processStarted) {
                throw $exception;
            }

            // Redis not available — process without throttling
            $process();
        }
    }

    /**
     * Whether this job runs on a real queue where release() re-queues it.
     */
    private function canBeReleased(): bool
    {
        return $this->job !== null && ! $this->job instanceof SyncJob;
    }

    /**
     * Re-queue the job, or count it as an error when it cannot be retried.
     */
    private function retryLaterOrFail(string $reason, int $delay = 5): void
    {
        if ($this->canBeReleased() && $this->attempts() < $this->tries) {
            $this->release($delay);

            return;
        }

        Log::warning('ProcessSoapClaveJob: no se pudo procesar la clave', [
            'clave' => $this->claveAcceso,
            'company_id' => $this->companyId,
            'scrape_type' => $this->scrapeType,
            'reason' => $reason,
        ]);

        $this->incrementScrapeJobResult('errors');
    }

    /**
     * Ask SRI for the authorization. On the sync queue release() is a no-op,
     * so retries happen inline with a short pause instead.
     *
     * @return object|array<int, object>|null
     */
    private function authorizeClave(SriSoapService $sriSoapService): mixed
    {
        $autorizacion = $sriSoapService->authorize($this->claveAcceso);

        if ($this->canBeReleased()) {
            return $autorizacion;
        }

        for ($retry = 1; $autorizacion === null && $retry < $this->tries; $retry++) {
            Sleep::for(2)->seconds();
            $autorizacion = $sriSoapService->authorize($this->claveAcceso);
        }

        return $autorizacion;
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
        $autorizacion = $this->authorizeClave($sriSoapService);

        if ($autorizacion === null) {
            $attempt = $this->attempts();
            $delay = $this->backoff[min($attempt - 1, count($this->backoff) - 1)] ?? 10;
            $this->retryLaterOrFail('SRI SOAP no respondió', $delay);

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
