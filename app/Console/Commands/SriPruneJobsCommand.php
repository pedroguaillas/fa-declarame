<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Tenant\SriScrapeJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('sri:prune-jobs
    {--status=failed : Estados a eliminar, separados por coma (failed,running,pending)}
    {--hours=1 : Horas mínimas en "running" para considerarse atascado (solo aplica a status=running)}
    {--days= : Eliminar solo jobs con más de N días de antigüedad (created_at)}
    {--tenant= : Limitar a un tenant específico por su ID}
    {--ids= : IDs específicos a eliminar, separados por coma (ignora los demás filtros)}
    {--dry-run : Solo listar los jobs que se eliminarían, sin borrar nada}
    {--force : No pedir confirmación antes de borrar}
')]
#[Description('Elimina SriScrapeJobs en estado failed y/o running-atascado para desbloquear reintentos de clientes.')]
class SriPruneJobsCommand extends Command
{
    private const ALLOWED_STATUSES = ['failed', 'running', 'pending'];

    public function handle(): int
    {
        $statuses = array_filter(array_map('trim', explode(',', (string) $this->option('status'))));
        $invalid = array_diff($statuses, self::ALLOWED_STATUSES);

        if ($invalid !== []) {
            $this->error('Estados inválidos: '.implode(', ', $invalid).'. Permitidos: '.implode(', ', self::ALLOWED_STATUSES).'.');

            return self::FAILURE;
        }

        $hours = (int) $this->option('hours');
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $tenantFilter = $this->option('tenant');
        $idsFilter = $this->option('ids')
            ? array_map('intval', explode(',', $this->option('ids')))
            : null;
        $cutoff = now()->subHours($hours);

        $action = $dryRun ? 'DRY-RUN' : 'eliminar';
        $scope = $tenantFilter ? " [tenant: {$tenantFilter}]" : '';

        if ($idsFilter) {
            $this->info('Buscando jobs por IDs: '.implode(', ', $idsFilter)."{$scope} — acción: {$action}");
        } else {
            $this->info('Buscando jobs en estado ['.implode(', ', $statuses)."]{$scope} — acción: {$action}");
        }

        $tenants = $tenantFilter
            ? Tenant::where('id', $tenantFilter)->get()
            : Tenant::all();

        if ($tenantFilter && $tenants->isEmpty()) {
            $this->error("Tenant '{$tenantFilter}' no encontrado.");

            return self::FAILURE;
        }

        $totalFound = 0;
        $totalDeleted = 0;
        $totalErrors = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $query = $idsFilter
                    ? SriScrapeJob::whereIn('id', $idsFilter)
                    : SriScrapeJob::where(function ($q) use ($statuses, $cutoff): void {
                        if (in_array('failed', $statuses, true)) {
                            $q->orWhere('status', 'failed');
                        }

                        if (in_array('running', $statuses, true)) {
                            $q->orWhere(function ($qq) use ($cutoff): void {
                                $qq->where('status', 'running')
                                    ->whereNull('completed_at')
                                    ->where('started_at', '<', $cutoff);
                            });
                        }

                        if (in_array('pending', $statuses, true)) {
                            $q->orWhere('status', 'pending');
                        }
                    });

                if (! $idsFilter && $days !== null) {
                    $query->where('created_at', '<', now()->subDays($days));
                }

                $jobs = $query->get();

                foreach ($jobs as $job) {
                    $totalFound++;
                    $this->line("  [{$tenant->id}] job #{$job->id} | company {$job->company_id} | {$job->status} | {$job->type} {$job->year}-{$job->month} | creado {$job->created_at}");
                }

                if ($dryRun || $jobs->isEmpty()) {
                    continue;
                }

                if (! $force && ! $this->confirm("¿Eliminar {$jobs->count()} job(s) del tenant {$tenant->id}?")) {
                    $this->warn('  Omitido por el usuario.');

                    continue;
                }

                foreach ($jobs as $job) {
                    try {
                        $job->delete();
                        $totalDeleted++;
                    } catch (\Throwable $e) {
                        $totalErrors++;
                        $this->error("    Error al eliminar job #{$job->id}: {$e->getMessage()}");

                        Log::error('sri:prune-jobs job error', [
                            'tenant_id' => $tenant->id,
                            'scrape_job_id' => $job->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                $totalErrors++;
                $this->error("  [{$tenant->id}] Error al inicializar tenant: {$e->getMessage()}");

                Log::error('sri:prune-jobs tenant error', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info("Encontrados: {$totalFound} jobs.");

        if (! $dryRun) {
            $this->info("Eliminados: {$totalDeleted} | Errores: {$totalErrors}");
        }

        return self::SUCCESS;
    }
}
