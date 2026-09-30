<?php

namespace App\Exports;

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

class ProductInventoryExport extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    use HasReportHeader;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        private readonly array $rows,
        private readonly ?string $logoPath = null,
        private readonly ?string $companyName = null,
    ) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return array_map(fn (array $row): array => [
            $row['code'],
            $row['aux_code'],
            $row['description'],
            $row['contact_identification'],
            $row['contact_name'],
            (float) $row['total_quantity'],
            (float) ($row['last_price'] ?? 0),
            (float) ($row['avg_price'] ?? 0),
            (float) $row['min_price'],
            (float) $row['max_price'],
            (float) $row['total_amount'],
            (int) $row['purchases_count'],
            $row['last_purchase_at'],
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'Código',
            'Código Auxiliar',
            'Producto',
            'RUC / Cédula',
            'Proveedor',
            'Cantidad',
            'Último Precio',
            'Precio Promedio',
            'Precio Mínimo',
            'Precio Máximo',
            'Total Comprado',
            'Compras',
            'Última Compra',
        ];
    }

    public function title(): string
    {
        return 'Inventario de Productos';
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'F' => '#,##0.00',
            'G' => '#,##0.000000',
            'H' => '#,##0.000000',
            'I' => '#,##0.000000',
            'J' => '#,##0.000000',
            'K' => NumberFormat::FORMAT_NUMBER_00,
            'M' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (in_array($cell->getColumn(), ['A', 'B', 'D', 'M'], true) && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 16, 'C' => 40, 'D' => 14, 'E' => 30, 'M' => 14];
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [];
    }
}
