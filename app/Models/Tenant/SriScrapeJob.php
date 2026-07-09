<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class SriScrapeJob extends Model
{
    public const MAX_FAILED_ATTEMPTS = 3;

    protected $fillable = [
        'company_id',
        'type',
        'year',
        'month',
        'day',
        'mode',
        'source',
        'voucher_types',
        'status',
        'progress',
        'result',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'day' => 'integer',
            'voucher_types' => 'array',
            'progress' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForPeriod(Builder $query, int $companyId, string $type, int $year, int $month, ?int $day): Builder
    {
        return $query->where('company_id', $companyId)
            ->where('type', $type)
            ->where('year', $year)
            ->where('month', $month)
            ->when(
                $day !== null,
                fn (Builder $q) => $q->where('day', $day),
                fn (Builder $q) => $q->whereNull('day'),
            );
    }

    /**
     * Determina si se debe bloquear una nueva descarga del período según los jobs previos.
     *
     * @param  Collection<int, self>  $previousJobs  Jobs completed/failed del mismo período
     */
    public static function blockReason(Collection $previousJobs): ?string
    {
        $satisfactory = $previousJobs->first(
            fn (self $job) => $job->status === 'completed' && (int) ($job->result['errors'] ?? 0) === 0
        );

        if ($satisfactory) {
            $imported = (int) ($satisfactory->result['imported'] ?? 0);
            $skipped = (int) ($satisfactory->result['skipped'] ?? 0);

            return "Este período ya fue descargado satisfactoriamente ({$imported} importados, {$skipped} omitidos). No se permite volver a descargarlo.";
        }

        $failedAttempts = $previousJobs->filter(
            fn (self $job) => $job->status === 'failed'
                || ($job->status === 'completed' && (int) ($job->result['errors'] ?? 0) > 0)
        )->count();

        if ($failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
            return 'Se alcanzó el máximo de '.self::MAX_FAILED_ATTEMPTS.' intentos para este período.';
        }

        return null;
    }
}
