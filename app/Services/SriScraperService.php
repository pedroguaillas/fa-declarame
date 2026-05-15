<?php

namespace App\Services;

use App\Models\Tenant\Company;
use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Order;
use App\Models\Tenant\Retention;
use App\Models\Tenant\Shop;
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

            // Skip claves already in the DB to avoid redundant SOAP calls / modal scrapes
            $config['skipClaves'] = $this->getExistingClavesForMonth($scrapeJob, $company);

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
            $retentionModalEntries = $file['retention_modal_entries'] ?? [];

            if ($file['status'] !== 'downloaded' || (empty($content) && empty($xmls) && empty($modalEntries) && empty($retentionModalEntries))) {
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

                // Retention modal entries (emitidos retenciones >30 days)
                if (! empty($retentionModalEntries)) {
                    $retStats = $this->importRetentionModalEntries($retentionModalEntries, $scrapeJob);
                    $stats['imported'] += $retStats['imported'];
                    $stats['skipped'] += $retStats['skipped'];
                    $stats['errors'] += $retStats['errors'];
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
                $tipoIdentRaw = strtoupper(trim($entry['tipo_identificacion_comprador'] ?? ''));

                $identificationTypeId = $this->resolveIdentificationTypeId($tipoIdentRaw, $buyerIdentification ?? '9999999999999');

                if ($buyerIdentification) {
                    $contact = Contact::firstOrCreate(
                        ['identification' => $buyerIdentification],
                        [
                            'identification_type_id' => $identificationTypeId,
                            'name' => $buyerName ?? $buyerIdentification,
                        ],
                    );
                } else {
                    $contact = Contact::firstOrCreate(
                        ['identification' => '9999999999999'],
                        [
                            'identification_type_id' => $identificationTypeId,
                            'name' => 'Consumidor Final',
                        ],
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
     * Import retention documents scraped from the SRI modal (emitidos >30 days).
     *
     * Each entry contains:
     *   clave, clave_acceso, establecimiento, punto_emision, secuencial,
     *   tipo_id_sujeto, id_sujeto, razon_social_sujeto,
     *   retenciones[]{impuesto, base_imponible, porcentaje_retenido, valor_retenido,
     *                  num_doc_sustento, fecha_doc_sustento}
     *
     * Each retention item references a specific shop (purchase) identified by
     * num_doc_sustento (formatted as serie) and fecha_doc_sustento.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array{imported: int, skipped: int, errors: int}
     */
    private function importRetentionModalEntries(array $entries, SriScrapeJob $scrapeJob): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($entries as $entry) {
            $claveAcceso = $entry['clave'] ?? $entry['clave_acceso'] ?? '';
            $establecimiento = $entry['establecimiento'] ?? '';
            $puntoEmision = $entry['punto_emision'] ?? '';
            $secuencial = $entry['secuencial'] ?? '';
            $retenciones = $entry['retenciones'] ?? [];

            if (empty($retenciones) || strlen($claveAcceso) !== 49) {
                $skipped++;

                continue;
            }

            // Derive retention document fields from the clave de acceso
            $serieRetention = "{$establecimiento}-{$puntoEmision}-{$secuencial}";
            $autorizacionRetention = $claveAcceso;

            // Date from first 8 digits of clave: ddmmYYYY
            try {
                $dd = substr($claveAcceso, 0, 2);
                $mm = substr($claveAcceso, 2, 2);
                $yyyy = substr($claveAcceso, 4, 4);
                $dateRetention = Carbon::createFromFormat('d/m/Y', "{$dd}/{$mm}/{$yyyy}")->format('Y-m-d');
            } catch (\Throwable) {
                $dateRetention = now()->format('Y-m-d');
            }

            // Group items by num_doc_sustento so each shop is updated once
            $grouped = [];
            foreach ($retenciones as $item) {
                $numDoc = trim($item['num_doc_sustento'] ?? '');
                if (empty($numDoc)) {
                    continue;
                }
                $grouped[$numDoc][] = $item;
            }

            foreach ($grouped as $numDoc => $items) {
                // Format num_doc_sustento as shop serie: NNN-NNN-NNNNNNNNN
                $serie = substr($numDoc, 0, 3).'-'.substr($numDoc, 3, 3).'-'.substr($numDoc, 6);

                // Parse fecha_doc_sustento from first item
                $fechaDocRaw = trim($items[0]['fecha_doc_sustento'] ?? '');
                $emisionDoc = null;
                if ($fechaDocRaw) {
                    try {
                        // Modal returns formats like "2026-04-07 00:00:00.0" or "2026-04-07"
                        $emisionDoc = Carbon::parse($fechaDocRaw)->format('Y-m-d');
                    } catch (\Throwable) {
                        // ignore, search without date
                    }
                }

                $shop = $emisionDoc
                    ? Shop::where('serie', $serie)->whereDate('emision', $emisionDoc)->first()
                    : Shop::where('serie', $serie)->first();

                if (! $shop) {
                    $skipped++;

                    continue;
                }

                $retentionItems = [];
                foreach ($items as $item) {
                    $tipoImpuesto = strtoupper(trim($item['impuesto'] ?? ''));
                    $porcentaje = (float) ($item['porcentaje_retenido'] ?? 0);
                    $base = (float) ($item['base_imponible'] ?? 0);
                    $valor = (float) ($item['valor_retenido'] ?? 0);

                    $retention = Retention::where('type', $tipoImpuesto)
                        ->where('percentage', $porcentaje)
                        ->first();

                    if (! $retention) {
                        Log::warning('SRI scrape: retention code not found for modal entry', [
                            'impuesto' => $tipoImpuesto,
                            'porcentaje' => $porcentaje,
                            'scrape_job_id' => $scrapeJob->id,
                        ]);

                        continue;
                    }

                    $retentionItems[] = [
                        'retention_id' => $retention->id,
                        'base' => $base,
                        'percentage' => $porcentaje,
                        'value' => $valor,
                    ];
                }

                if (empty($retentionItems)) {
                    $skipped++;

                    continue;
                }

                $shop->update([
                    'serie_retention' => $serieRetention,
                    'date_retention' => $dateRetention,
                    'autorization_retention' => $autorizacionRetention,
                    'state_retention' => 'AUTORIZADO',
                    'retention_at' => $dateRetention,
                ]);

                $shop->retentionItems()->delete();
                $shop->retentionItems()->createMany($retentionItems);

                $imported++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Resolve the IdentificationType DB id from a raw SRI label or code.
     *
     * Tries in order:
     *   1. code_order match (e.g. "04", "05", "06")
     *   2. description match (e.g. "RUC", "CEDULA", "PASAPORTE")
     *   3. Identification length fallback: 13 digits → RUC (04), 10 → Cédula (05), other → Pasaporte (06)
     */
    private function resolveIdentificationTypeId(string $tipoRaw, string $identification): ?int
    {
        static $cache = [];

        $cacheKey = $tipoRaw ?: ('len:'.strlen($identification));
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $id = null;

        if ($tipoRaw !== '') {
            $id = IdentificationType::where('code_order', $tipoRaw)->value('id')
                ?? IdentificationType::whereRaw('UPPER(description) LIKE ?', ['%'.strtoupper($tipoRaw).'%'])->value('id');
        }

        if (! $id) {
            $code = match (strlen($identification)) {
                13 => '04',
                10 => '05',
                default => '06',
            };
            $id = IdentificationType::where('code_order', $code)->value('id');
        }

        return $cache[$cacheKey] = $id;
    }

    /**
     * Return all access keys (claves de acceso) already stored in the DB for the
     * given job's month/year so the Python scraper can skip redundant processing.
     *
     * ventas: Orders (Facturas/NC/ND emitidas) + Shop.autorization_retention (Retenciones emitidas)
     * compras: Shops (Facturas/NC/ND recibidas)
     *
     * @return array<int, string>
     */
    private function getExistingClavesForMonth(SriScrapeJob $scrapeJob, Company $company): array
    {
        $year = $scrapeJob->year;
        $month = $scrapeJob->month;

        if ($scrapeJob->type === 'ventas') {
            $orderClaves = Order::where('company_id', $company->id)
                ->whereYear('emision', $year)
                ->whereMonth('emision', $month)
                ->whereNotNull('autorization')
                ->pluck('autorization')
                ->all();

            $retentionClaves = Shop::where('company_id', $company->id)
                ->whereYear('date_retention', $year)
                ->whereMonth('date_retention', $month)
                ->whereNotNull('autorization_retention')
                ->pluck('autorization_retention')
                ->all();

            return array_values(array_unique(array_merge($orderClaves, $retentionClaves)));
        }

        // compras: facturas/NC/ND recibidas (Shop) + retenciones recibidas (Order)
        $shopClaves = Shop::where('company_id', $company->id)
            ->whereYear('emision', $year)
            ->whereMonth('emision', $month)
            ->whereNotNull('autorization')
            ->pluck('autorization')
            ->all();

        $orderRetentionClaves = Order::where('company_id', $company->id)
            ->whereYear('emision', $year)
            ->whereMonth('emision', $month)
            ->whereNotNull('autorization_retention')
            ->pluck('autorization_retention')
            ->all();

        return array_values(array_unique(array_merge($shopClaves, $orderRetentionClaves)));
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
