<?php

namespace App\Exports\Semester;

use App\Exports\Concerns\HasReportHeader;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SemesterSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use HasReportHeader;

    /** @param array{compras: array<string, mixed>, ventas: array<string, mixed>} $resumen */
    public function __construct(
        private readonly array $resumen,
        private readonly string $periodLabel,
        private readonly ?string $logoPath = null,
        private readonly ?string $companyName = null,
    ) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $n = fn (mixed $v): float => (float) ($v ?? 0);
        $compras = $this->resumen['compras'];
        $ventas = $this->resumen['ventas'];

        return [
            [
                'Compras',
                (int) $compras['count'],
                $n($compras['subtotal']),
                $n($compras['iva']),
                $n($compras['total']),
                $n($compras['retentions']),
                $n($compras['a_pagar'] ?? 0),
            ],
            [
                'Ventas',
                (int) $ventas['count'],
                $n($ventas['subtotal']),
                $n($ventas['iva']),
                $n($ventas['total']),
                $n($ventas['retentions']),
                $n($ventas['a_cobrar'] ?? 0),
            ],
        ];
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Concepto', 'Comprobantes', 'Base Imponible', 'IVA', 'Total', 'Retenciones', 'Neto'];
    }

    public function title(): string
    {
        return "Resumen {$this->periodLabel}";
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 14, 'C' => 14, 'D' => 12, 'E' => 14, 'F' => 14, 'G' => 14];
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [];
    }
}
