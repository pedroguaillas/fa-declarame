<?php

namespace App\Exports;

use App\Exports\Semester\SemesterComprasSheet;
use App\Exports\Semester\SemesterRetencionesEmitidasSheet;
use App\Exports\Semester\SemesterRetencionesRecibidasSheet;
use App\Exports\Semester\SemesterSummarySheet;
use App\Exports\Semester\SemesterVentasSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SemesterReportExport implements WithMultipleSheets
{
    /**
     * @param  array{compras: array<string, mixed>, ventas: array<string, mixed>}  $resumen
     * @param  array<int, array<string, mixed>>  $compras
     * @param  array<int, array<string, mixed>>  $ventas
     * @param  array<int, array<string, mixed>>  $retencionesRecibidas
     * @param  array<int, array<string, mixed>>  $retencionesEmitidas
     */
    public function __construct(
        private readonly int $year,
        private readonly int $semester,
        private readonly array $resumen,
        private readonly array $compras,
        private readonly array $ventas,
        private readonly array $retencionesRecibidas,
        private readonly array $retencionesEmitidas,
        private readonly ?string $logoPath = null,
        private readonly ?string $companyName = null,
    ) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            new SemesterSummarySheet($this->resumen, $this->year, $this->semester, $this->logoPath, $this->companyName),
            new SemesterComprasSheet($this->compras, $this->logoPath, $this->companyName),
            new SemesterVentasSheet($this->ventas, $this->logoPath, $this->companyName),
            new SemesterRetencionesRecibidasSheet($this->retencionesRecibidas, $this->logoPath, $this->companyName),
            new SemesterRetencionesEmitidasSheet($this->retencionesEmitidas, $this->logoPath, $this->companyName),
        ];
    }
}
