<?php

namespace App\Http\Controllers\Tenant\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait FiltersByPeriod
{
    /**
     * Apply a period filter on emision. Supports 'YYYY-MM' (month)
     * and 'YYYY-S1' / 'YYYY-S2' (semester).
     */
    private function applyPeriodFilter(Builder $query, string $period): Builder
    {
        if (preg_match('/^(\d{4})-S([12])$/', $period, $matches) === 1) {
            [$startDate, $endDate] = $this->semesterRange((int) $matches[1], (int) $matches[2]);

            return $query->whereBetween('emision', [$startDate, $endDate]);
        }

        return $query->whereYear('emision', substr($period, 0, 4))
            ->whereMonth('emision', substr($period, 5, 2));
    }

    /**
     * Default period: previous month/semester when it has records,
     * otherwise the period of the last emision.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function defaultPeriod(string $modelClass, bool $isSemiannual): string
    {
        $now = now('America/Guayaquil');

        if (! $isSemiannual) {
            $previousMonth = $now->copy()->subMonth();
            $hasPreviousMonth = $modelClass::whereYear('emision', $previousMonth->year)
                ->whereMonth('emision', $previousMonth->month)
                ->exists();

            if ($hasPreviousMonth) {
                return $previousMonth->format('Y-m');
            }

            $lastEmision = $modelClass::max('emision');

            return $lastEmision ? substr($lastEmision, 0, 7) : $now->format('Y-m');
        }

        [$year, $semester] = $now->month <= 6 ? [$now->year - 1, 2] : [$now->year, 1];
        [$startDate, $endDate] = $this->semesterRange($year, $semester);

        if ($modelClass::whereBetween('emision', [$startDate, $endDate])->exists()) {
            return "{$year}-S{$semester}";
        }

        $lastEmision = $modelClass::max('emision');

        if ($lastEmision) {
            $lastYear = (int) substr($lastEmision, 0, 4);
            $lastSemester = (int) substr($lastEmision, 5, 2) <= 6 ? 1 : 2;

            return "{$lastYear}-S{$lastSemester}";
        }

        return $now->year.'-S'.($now->month <= 6 ? 1 : 2);
    }

    /** @return array{string, string} [startDate, endDate] Y-m-d */
    private function semesterRange(int $year, int $semester): array
    {
        [$startMonth, $endMonth] = $semester === 1 ? [1, 6] : [7, 12];

        return [
            sprintf('%d-%02d-01', $year, $startMonth),
            Carbon::create($year, $endMonth)->endOfMonth()->format('Y-m-d'),
        ];
    }
}
