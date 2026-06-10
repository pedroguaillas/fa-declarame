<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeFromSriJob;
use App\Models\Tenant;
use App\Models\Tenant\SriScrapeJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('sri:rescue-stuck-jobs
    {--hours=1 : Horas mínimas que un job debe llevar en "running" para considerarse atascado}
    {--date= : Filtrar solo jobs atascados en un día específico (formato: YYYY-MM-DD)}
    {--mark-failed : Marcar como failed en lugar de re-despachar}
    {--dry-run : Solo listar los jobs atascados sin realizar ninguna acción}
')]
#[Description('Rescata SriScrapeJobs atascados en "running" tras un reinicio del servidor Python.')]
class SriRescueStuckJobsCommand extends Command
{
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $markFailed = $this->option('mark-failed');
        $dryRun = $this->option('dry-run');
        $dateFilter = $this->option('date');
        $cutoff = now()->subHours($hours);

        $action = $dryRun ? 'DRY-RUN' : ($markFailed ? 'marcar como failed' : 're-despachar');

        if ($dateFilter) {
            $this->info("Buscando jobs atascados en 'running' del día {$dateFilter} — acción: {$action}");
        } else {
            $this->info("Buscando jobs atascados en 'running' desde hace más de {$hours}h — acción: {$action}");
        }

        $tenants = Tenant::all();
        $totalFound = 0;
        $totalActioned = 0;
        $totalErrors = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $stuck = SriScrapeJob::where('status', 'running')
                    ->whereNull('completed_at')
                    ->when($dateFilter, function ($query) use ($dateFilter): void {
                        $query->whereDate('started_at', $dateFilter);
                    }, function ($query) use ($cutoff): void {
                        $query->where('started_at', '<', $cutoff);
                    })
                    ->get();

                foreach ($stuck as $job) {
                    $totalFound++;
                    $this->line("  [{$tenant->id}] job #{$job->id} | company {$job->company_id} | {$job->type} {$job->year}-{$job->month} | desde {$job->started_at}");

                    if ($dryRun) {
                        continue;
                    }

                    try {
                        if ($markFailed) {
                            $job->update([
                                'status' => 'failed',
                                'error_message' => 'Job interrumpido por reinicio del servidor Python. Reintente manualmente.',
                                'completed_at' => now(),
                            ]);
                        } else {
                            $job->update([
                                'status' => 'pending',
                                'started_at' => null,
                                'progress' => null,
                            ]);

                            ScrapeFromSriJob::dispatch($job->id, $job->company_id, $tenant->getTenantKey());
                        }

                        $totalActioned++;
                    } catch (\Throwable $e) {
                        $totalErrors++;
                        $this->error("    Error al procesar job #{$job->id}: {$e->getMessage()}");

                        Log::error('sri:rescue-stuck-jobs job error', [
                            'tenant_id' => $tenant->id,
                            'scrape_job_id' => $job->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $totalErrors++;
                $this->error("  [{$tenant->id}] Error al inicializar tenant: {$e->getMessage()}");

                Log::error('sri:rescue-stuck-jobs tenant error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info("Encontrados: {$totalFound} jobs atascados.");

        if (! $dryRun) {
            $this->info("Accionados: {$totalActioned} | Errores: {$totalErrors}");
        }

        return self::SUCCESS;
    }
}
