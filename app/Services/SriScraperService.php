<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\SriScrapeJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SriScraperService
{
    /** @var array<int, string> Voucher types to scrape from SRI */
    private const VOUCHER_TYPES = [
        1 => 'Factura',
        3 => 'Notas de Crédito',
        4 => 'Notas de Débito',
        6 => 'Comprobante de Retención',
    ];

    public function __construct(
        private readonly OrderImportService $orderImportService,
        private readonly ShopImportService $shopImportService,
        private readonly OrderRetentionImportService $orderRetentionImportService,
        private readonly ShopRetentionImportService $shopRetentionImportService,
        private readonly SriSoapService $sriSoapService,
        private readonly SriXmlParserService $xmlParser,
    ) {}

    /**
     * Execute the scraper and return the result.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    public function execute(SriScrapeJob $scrapeJob, Company $company): array
    {
        $scrapeJob->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $downloadDir = storage_path('app/private/sri-scrape/'.$scrapeJob->id);

        try {
            $serverUrl = config('sri.scraper.server_url');

            $config = [
                'ruc' => $company->ruc,
                'password' => $company->pass_sri,
                'type' => $scrapeJob->type,
                'year' => $scrapeJob->year,
                'month' => $scrapeJob->month,
                'mode' => $scrapeJob->mode,
                'voucherTypes' => $scrapeJob->voucher_types ?? ['1', '3', '4'],
                'headless' => config('sri.scraper.headless', true),
            ];

            // Only pass downloadDir when running locally (not via external server)
            if (! $serverUrl) {
                $config['downloadDir'] = $downloadDir;
            }

            // 2Captcha API key is optional (stealth may bypass captcha without it)
            $captchaKey = config('sri.captcha.api_key');
            if ($captchaKey) {
                $config['apiKey'] = $captchaKey;
            }

            $result = $serverUrl
                ? $this->runViaServer($serverUrl, $config, $scrapeJob)
                : $this->runNodeScript($config, $scrapeJob);

            return $this->processResult($result, $scrapeJob, $company);

        } catch (\Throwable $e) {
            $scrapeJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::error('SRI Scraper failed', [
                'scrape_job_id' => $scrapeJob->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Cleanup temp directory
            if (is_dir($downloadDir)) {
                $files = glob($downloadDir.'/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($downloadDir);
            }
        }
    }

    /**
     * Send scrape request to the running Python server (--visible mode).
     */
    private function runViaServer(string $serverUrl, array $config, SriScrapeJob $scrapeJob): array
    {
        $timeout = config('sri.scraper.timeout', 300);

        $scrapeJob->update([
            'progress' => ['step' => 'server', 'message' => 'Enviando petición al servidor del scraper...'],
        ]);

        $response = Http::timeout($timeout)
            ->post(rtrim($serverUrl, '/').'/scrape', $config);

        if ($response->failed()) {
            $error = $response->json('error') ?? $response->body();

            throw new \RuntimeException("Scraper server error ({$response->status()}): {$error}");
        }

        $body = $response->json();

        if (($body['event'] ?? null) === 'error') {
            throw new \RuntimeException($body['data']['message'] ?? 'Error desconocido del scraper');
        }

        return $body['data'] ?? [];
    }

    private function runNodeScript(array $config, SriScrapeJob $scrapeJob): array
    {
        $engine = config('sri.scraper.engine', 'python');
        $timeout = config('sri.scraper.timeout', 300);

        $headless = config('sri.scraper.headless', true);

        if ($engine === 'python') {
            $pythonPath = config('sri.scraper.python_path', 'python3');
            $scriptPath = config('sri.scraper.python_script_path');
            $command = $headless
                ? [$pythonPath, $scriptPath]
                : ['xvfb-run', '--auto-servernum', '--server-args=-screen 0 1366x768x24', $pythonPath, $scriptPath];
            $process = new Process($command);

            // Add user data dir for session persistence (stealth layer)
            $userDataDir = config('sri.scraper.user_data_dir');
            if ($userDataDir) {
                $config['userDataDir'] = $userDataDir;
            }
        } else {
            $nodePath = config('sri.scraper.node_path', 'node');
            $scriptPath = config('sri.scraper.script_path');
            $process = new Process([$nodePath, $scriptPath]);
        }

        $process->setTimeout($timeout);
        $process->setInput(json_encode($config));

        $chromePath = config('sri.scraper.chrome_path');
        if ($chromePath) {
            $env = $engine === 'python'
                ? ['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' => $chromePath]
                : ['PUPPETEER_EXECUTABLE_PATH' => $chromePath];
            $process->setEnv($env);
        }

        $lastResult = null;
        $lastError = null;
        $stderrLog = '';

        $process->run(function ($type, $buffer) use ($scrapeJob, &$lastResult, &$lastError, &$stderrLog) {
            if ($type === Process::ERR) {
                $stderrLog .= $buffer;

                return;
            }

            if ($type !== Process::OUT) {
                return;
            }

            foreach (explode("\n", trim($buffer)) as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $event = json_decode($line, true);
                if (! $event) {
                    continue;
                }

                match ($event['event'] ?? null) {
                    'progress' => $scrapeJob->update([
                        'progress' => $event['data'],
                    ]),
                    'result' => $lastResult = $event['data'],
                    'error' => $lastError = $event['data'],
                    default => null,
                };
            }
        });

        // Log stderr diagnostics
        if ($stderrLog) {
            Log::info('SRI Scraper stderr', [
                'scrape_job_id' => $scrapeJob->id,
                'log' => $stderrLog,
            ]);
        }

        if ($lastError) {
            throw new \RuntimeException($lastError['message'] ?? 'Error desconocido del scraper');
        }

        if (! $process->isSuccessful() && ! $lastResult) {
            throw new \RuntimeException('El script del scraper falló: '.$process->getErrorOutput());
        }

        return $lastResult ?? [];
    }

    /**
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function processResult(array $result, SriScrapeJob $scrapeJob, Company $company): array
    {
        $mode = $result['mode'] ?? $scrapeJob->mode;

        if ($mode === 'txt_download') {
            return $this->processTxtDownload($result, $scrapeJob, $company);
        }

        return $this->processTableScrape($result, $scrapeJob, $company);
    }

    /**
     * Process multiple .txt files (one per voucher type) from the scraper.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function processTxtDownload(array $result, SriScrapeJob $scrapeJob, Company $company): array
    {
        $files = $result['files'] ?? [];
        $totalImported = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $fileResults = [];

        foreach ($files as $file) {
            $type = $file['type'] ?? 'unknown';
            $content = $file['content'] ?? '';

            if ($file['status'] !== 'downloaded' || empty($content)) {
                $fileResults[$type] = $file['status'] ?? 'no_content';

                continue;
            }

            $scrapeJob->update([
                'progress' => [
                    'step' => 'import',
                    'message' => "Importando {$type}...",
                ],
            ]);

            try {
                $stats = $this->importContent($content, $type, $scrapeJob->type, $company);
                $totalImported += $stats['imported'];
                $totalSkipped += $stats['skipped'];
                $totalErrors += $stats['errors'] ?? 0;
                $fileResults[$type] = $stats;
            } catch (\Throwable $e) {
                $totalErrors++;
                $fileResults[$type] = 'error: '.$e->getMessage();
                Log::warning('SRI scrape: failed to import file', [
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $stats = [
            'imported' => $totalImported,
            'skipped' => $totalSkipped,
            'errors' => $totalErrors,
            'details' => $fileResults,
        ];

        $scrapeJob->update([
            'status' => 'completed',
            'result' => $stats,
            'completed_at' => now(),
        ]);

        return $stats;
    }

    /**
     * Route content to the appropriate import service based on voucher type.
     *
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function importContent(string $content, string $voucherType, string $scrapeType, Company $company): array
    {
        // Retenciones recibidas van a ventas (OrderRetention), retenciones emitidas van a compras (ShopRetention)
        if ($voucherType === 'Retencion') {
            $retentionService = $scrapeType === 'compras'
                ? $this->orderRetentionImportService
                : $this->shopRetentionImportService;

            return $retentionService->import($content, $company->ruc);
        }

        // Facturas, NC, ND: recibidos → compras (Shop), emitidos → ventas (Order)
        $importService = $scrapeType === 'ventas'
            ? $this->orderImportService
            : $this->shopImportService;

        return $importService->import($content, $company->id, $company->ruc);
    }

    /**
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function processTableScrape(array $result, SriScrapeJob $scrapeJob, Company $company): array
    {
        $claves = $result['clavesAcceso'] ?? [];
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($claves as $index => $clave) {
            $scrapeJob->update([
                'progress' => [
                    'step' => 'import',
                    'message' => 'Procesando clave '.($index + 1).'/'.count($claves),
                ],
            ]);

            try {
                $autorizacion = $this->sriSoapService->authorize($clave);

                if ($autorizacion === null) {
                    $skipped++;

                    continue;
                }

                $sriData = $this->xmlParser->parse($autorizacion);

                if ($sriData === null) {
                    $skipped++;

                    continue;
                }

                // TODO: Create order/shop record from parsed data
                // This will depend on the voucher type and whether it's compras/ventas
                $imported++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('SRI scrape: failed to process clave', [
                    'clave' => $clave,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $stats = ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];

        $scrapeJob->update([
            'status' => 'completed',
            'result' => $stats,
            'completed_at' => now(),
        ]);

        return $stats;
    }
}
