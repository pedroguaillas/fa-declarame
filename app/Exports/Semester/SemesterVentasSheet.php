<?php

namespace App\Exports\Semester;

use App\Exports\Concerns\HasReportHeader;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SemesterVentasSheet extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use HasReportHeader;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(
        private readonly array $rows,
        private readonly ?string $logoPath = null,
        private readonly ?string $companyName = null,
    ) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $n = fn (mixed $v): float => (float) ($v ?? 0);

        return array_map(fn (array $row) => [
            $row['emision'],
            $row['voucher_type'],
            $row['serie'],
            $row['identification'],
            $row['name'],
            $n($row['sub_total']),
            $n($row['no_iva']),
            $n($row['base0']),
            $n($row['base5']),
            $n($row['base12']),
            $n($row['base15']),
            $n($row['iva5']),
            $n($row['iva12']),
            $n($row['iva15']),
            $n($row['total']),
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Emisión', 'Tipo Comprobante', 'Serie', 'RUC / Cédula', 'Cliente',
            'Sub Total', 'No IVA', 'Base 0%', 'Base 5%', 'Base 12%', 'Base 15%',
            'IVA 5%', 'IVA 12%', 'IVA 15%', 'Total',
        ];
    }

    public function title(): string
    {
        return 'Ventas';
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['D' => NumberFormat::FORMAT_TEXT];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getColumn() === 'D' && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 22, 'C' => 18, 'D' => 14, 'E' => 30];
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [];
    }
}
