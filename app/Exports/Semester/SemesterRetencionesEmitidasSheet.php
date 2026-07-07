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

class SemesterRetencionesEmitidasSheet extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
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
            $row['date_retention'],
            $row['serie_retention'],
            $row['voucher'],
            $row['identification'],
            $row['name'],
            $row['code'],
            $row['type'],
            $row['description'],
            $n($row['base']),
            $n($row['percentage']),
            $n($row['value']),
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Fecha Retención', 'Serie Retención', 'Comprobante Compra', 'RUC / Cédula', 'Proveedor',
            'Código', 'Impuesto', 'Descripción', 'Base', 'Porcentaje', 'Valor',
        ];
    }

    public function title(): string
    {
        return 'Retenciones Emitidas';
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
        return ['A' => 14, 'B' => 18, 'C' => 18, 'D' => 14, 'E' => 30, 'F' => 10, 'G' => 10, 'H' => 40];
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [];
    }
}
