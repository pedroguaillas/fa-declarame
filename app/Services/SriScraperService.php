<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Order;
use App\Models\Tenant\SriScrapeJob;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;
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
            $xmls = $file['xmls'] ?? [];
            $modalEntries = $file['modal_entries'] ?? [];

            if ($file['status'] !== 'downloaded' || (empty($content) && empty($xmls) && empty($modalEntries))) {
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
                $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];

                // Direct XML import for claves >30 days (compras / ventas modal with XML)
                if (! empty($xmls)) {
                    $xmlStats = $this->importXmlsDirectly($xmls, $type, $scrapeJob->type, $company);
                    $stats['imported'] += $xmlStats['imported'];
                    $stats['skipped'] += $xmlStats['skipped'];
                    $stats['errors'] += $xmlStats['errors'];
                }

                // Modal-scraped data import for ventas claves >30 days (no XML available)
                if (! empty($modalEntries)) {
                    $modalStats = $this->importModalEntries($modalEntries, $company);
                    $stats['imported'] += $modalStats['imported'];
                    $stats['skipped'] += $modalStats['skipped'];
                    $stats['errors'] += $modalStats['errors'];
                }

                // SOAP import for claves ≤30 days
                if (! empty($content)) {
                    $soapStats = $this->importContent($content, $type, $scrapeJob->type, $company);
                    $stats['imported'] += $soapStats['imported'];
                    $stats['skipped'] += $soapStats['skipped'];
                    $stats['errors'] += $soapStats['errors'] ?? 0;
                }

                $totalImported += $stats['imported'];
                $totalSkipped += $stats['skipped'];
                $totalErrors += $stats['errors'];
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
     * Import XMLs downloaded directly from the SRI table (claves older than 30 days).
     *
     * @param  array<int, array{clave: string, xml: string}>  $xmls
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function importXmlsDirectly(array $xmls, string $voucherType, string $scrapeType, Company $company): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($xmls as $entry) {
            $xmlContent = $entry['xml'] ?? '';

            if (empty($xmlContent)) {
                $skipped++;

                continue;
            }

            try {
                if ($voucherType === 'Retencion') {
                    // Retention XMLs are not yet handled via direct download
                    $skipped++;

                    continue;
                }

                $stats = $scrapeType === 'compras'
                    ? $this->shopImportService->importFromXml($xmlContent, $company->id, $company->ruc)
                    : $this->orderImportService->importFromXml($xmlContent, $company->id, $company->ruc);

                $imported += $stats['imported'];
                $skipped += $stats['skipped'];
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('SRI scrape: failed to import XML directly', [
                    'clave' => $entry['clave'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Import ventas (emitidos) entries whose data was scraped from the SRI
     * 'Detalle del Comprobante' modal (claves older than 30 days).
     *
     * Each entry contains:
     *   clave, txt_line, clave_acceso, establecimiento, punto_emision,
     *   secuencial, tipo_identificacion_comprador, razon_social_comprador,
     *   identificacion_comprador, total_sin_impuestos, total_descuento,
     *   importe_total, impuestos[]{codigo, base_imponible, valor}
     *
     * SRI IVA tariff codes: 0=0%, 2=12%, 3=exento, 4=15%, 5=5%, 6=8%
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function importModalEntries(array $entries, Company $company): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($entries as $entry) {
            // Prefer the modal's clave_acceso; fall back to the row's clave key
            $claveAcceso = $entry['clave_acceso'] ?? $entry['clave'] ?? '';

            if (strlen($claveAcceso) !== 49) {
                $skipped++;

                continue;
            }

            if (Order::where('autorization', $claveAcceso)->exists()) {
                $skipped++;

                continue;
            }

            try {
                // ── Serie from modal fields ──
                $establecimiento = $entry['establecimiento'] ?? '';
                $puntoEmision = $entry['punto_emision'] ?? '';
                $secuencial = $entry['secuencial'] ?? '';
                $serie = $establecimiento && $puntoEmision && $secuencial
                    ? "{$establecimiento}-{$puntoEmision}-{$secuencial}"
                    : trim(explode("\t", $entry['txt_line'] ?? '')[1] ?? '');

                // ── Dates from txt line (cols 3 & 4) ──
                $txtCols = $entry['txt_line'] ? explode("\t", $entry['txt_line']) : [];
                $fechaAutorizacion = trim($txtCols[3] ?? '');
                $fechaEmision = trim($txtCols[4] ?? '');

                // ── Financial totals from modal ──
                $subTotal = (float) ($entry['total_sin_impuestos'] ?? $txtCols[5] ?? 0);
                $total = (float) ($entry['importe_total'] ?? $txtCols[7] ?? 0);
                $discount = (float) ($entry['total_descuento'] ?? 0);

                // ── IVA breakdown from Totales por Impuesto table ──
                // SRI tariff codes: 0→0%, 2→12%, 3→exento, 4→15%, 5→5%, 6→8%
                $base0 = $base5 = $base8 = $base12 = $base15 = 0.0;
                $noIva = 0.0;
                $iva5 = $iva8 = $iva12 = $iva15 = 0.0;

                foreach (($entry['impuestos'] ?? []) as $imp) {
                    $codigo = (int) round((float) ($imp['codigo'] ?? 0));
                    $base = (float) ($imp['base_imponible'] ?? 0);
                    $valor = (float) ($imp['valor'] ?? 0);

                    match ($codigo) {
                        0 => $base0 += $base,
                        2 => [$base12, $iva12] = [$base12 + $base, $iva12 + $valor],
                        3 => $noIva += $base,
                        4 => [$base15, $iva15] = [$base15 + $base, $iva15 + $valor],
                        5 => [$base5, $iva5] = [$base5 + $base, $iva5 + $valor],
                        6 => [$base8, $iva8] = [$base8 + $base, $iva8 + $valor],
                        default => null,
                    };
                }

                // ── Buyer contact ──
                $buyerIdentification = $entry['identificacion_comprador'] ?? null;
                $buyerName = $entry['razon_social_comprador'] ?? null;

                if ($buyerIdentification) {
                    $contact = Contact::firstOrCreate(
                        ['identification' => $buyerIdentification],
                        ['name' => $buyerName ?? $buyerIdentification],
                    );
                } else {
                    $contact = Contact::firstOrCreate(
                        ['identification' => '9999999999999'],
                        ['name' => 'Consumidor Final'],
                    );
                }

                // ── Dates ──
                $emision = null;
                if ($fechaEmision) {
                    try {
                        $emision = Carbon::createFromFormat('d/m/Y H:i:s', $fechaEmision)->format('Y-m-d');
                    } catch (\Throwable) {
                        $emision = now()->format('Y-m-d');
                    }
                }

                $autorizedAt = null;
                if ($fechaAutorizacion) {
                    try {
                        $autorizedAt = Carbon::createFromFormat('d/m/Y H:i:s', $fechaAutorizacion)->format('Y-m-d H:i:s');
                    } catch (\Throwable) {
                        $autorizedAt = now()->format('Y-m-d H:i:s');
                    }
                }

                $voucherTypeId = VoucherType::where('code', substr($claveAcceso, 8, 2))->value('id');

                Order::create([
                    'company_id' => $company->id,
                    'contact_id' => $contact->id,
                    'voucher_type_id' => $voucherTypeId,
                    'emision' => $emision,
                    'autorization' => $claveAcceso,
                    'autorized_at' => $autorizedAt,
                    'serie' => $serie,
                    'sub_total' => $subTotal,
                    'base0' => $base0,
                    'no_iva' => $noIva,
                    'base5' => $base5,
                    'base8' => $base8,
                    'base12' => $base12,
                    'base15' => $base15,
                    'iva5' => $iva5,
                    'iva8' => $iva8,
                    'iva12' => $iva12,
                    'iva15' => $iva15,
                    'discount' => $discount,
                    'total' => $total,
                    'state' => 'AUTORIZADO',
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('SRI scrape: failed to import modal entry', [
                    'clave' => $claveAcceso,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
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
