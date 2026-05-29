<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeFromSriJob;
use App\Models\Tenant;
use App\Models\Tenant\Company;
use App\Models\Tenant\SriScrapeJob;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('sri:daily-scrape {--date= : Fecha a consultar en formato YYYY-MM-DD (default: ayer)}')]
#[Description('Descarga automática diaria de comprobantes electrónicos del SRI para todas las empresas.')]
class SriDailyScrapeCommand extends Command
{
    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'), 'America/Guayaquil')
            : Carbon::yesterday('America/Guayaquil');

        $year = (int) $date->year;
        $month = (int) $date->month;
        $day = (int) $date->day;

        $this->info("Iniciando scrape automático para {$date->toDateString()}...");

        $tenants = Tenant::all();
        $totalDispatched = 0;
        $totalSkipped = 0;

        $totalErrors = 0;

        // Seconds between each company scrape to avoid SRI captcha on concurrent sessions
        $delaySeconds = (int) config('sri.scraper.daily_dispatch_interval', 90);
        $dispatchIndex = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $companies = Company::whereNotNull('pass_sri')
                    ->where('pass_sri', '!=', '')
                    ->get(['id', 'ruc']);

                foreach ($companies as $company) {
                    try {
                        $alreadyExists = SriScrapeJob::where('company_id', $company->id)
                            ->where('type', 'ambos')
                            ->where('year', $year)
                            ->where('month', $month)
                            ->where('day', $day)
                            ->whereIn('status', ['pending', 'running', 'completed'])
                            ->exists();

                        if ($alreadyExists) {
                            $totalSkipped++;

                            continue;
                        }

                        $scrapeJob = SriScrapeJob::create([
                            'company_id' => $company->id,
                            'type' => 'ambos',
                            'year' => $year,
                            'month' => $month,
                            'day' => $day,
                            'mode' => 'txt_download',
                            'source' => 'automatic',
                            'voucher_types' => ['1'],
                            'status' => 'pending',
                        ]);

                        ScrapeFromSriJob::dispatch($scrapeJob->id, $company->id, $tenant->getTenantKey())
                            ->delay(now()->addSeconds($dispatchIndex * $delaySeconds));

                        $dispatchIndex++;

                        $totalDispatched++;
                        $this->line("  [{$tenant->id}] RUC {$company->ruc} → job #{$scrapeJob->id}");
                    } catch (\Throwable $e) {
                        $totalErrors++;
                        $this->error("  [{$tenant->id}] RUC {$company->ruc} error: {$e->getMessage()}");

                        Log::error('sri:daily-scrape company error', [
                            'tenant_id' => $tenant->id,
                            'company_id' => $company->id,
                            'ruc' => $company->ruc,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $totalErrors++;
                $this->error("  [{$tenant->id}] Error al inicializar tenant: {$e->getMessage()}");

                Log::error('sri:daily-scrape tenant error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Completado: {$totalDispatched} jobs despachados, {$totalSkipped} omitidos, {$totalErrors} tenants con error.");

        return self::SUCCESS;
    }
}
