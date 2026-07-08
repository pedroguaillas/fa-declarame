<?php

namespace App\Exports;

use App\Exports\Concerns\HasReportHeader;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersByRetentionExport implements FromArray, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use HasReportHeader;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private readonly array $rows, private readonly ?string $logoPath = null, private readonly ?string $companyName = null) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['code'],
            $row['description'],
            ($row['percentage'] ?? 0).'%',
            (float) ($row['base'] ?? 0),
            (float) ($row['value'] ?? 0),
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Código', 'Descripción', 'Retenido %', 'Base', 'Valor'];
    }

    public function title(): string
    {
        return 'Ventas por Retenciones';
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['B' => 30];
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        $centerH = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];

        $sheet->getStyle('A')->applyFromArray($centerH);
        $sheet->getStyle('C')->applyFromArray($centerH);

        return [];
    }
}
