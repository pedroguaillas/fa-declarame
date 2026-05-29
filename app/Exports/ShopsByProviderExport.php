<?php

namespace App\Exports;

use Constants;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopsByProviderExport extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    private bool $showNewRates;

    private bool $showOldRates;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{start_date?: string|null, end_date?: string|null}  $filters
     */
    public function __construct(private readonly array $rows, array $filters = [])
    {
        $endDate = $filters['end_date'] ?? null;
        $startDate = $filters['start_date'] ?? null;

        $this->showNewRates = $endDate === null || $endDate >= Constants::IVA_NEW_RATES_START;
        $this->showOldRates = $startDate === null || $startDate < Constants::IVA_NEW_RATES_START;
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $n = fn (mixed $v): float => (float) ($v ?? 0);

        return array_map(function (array $row) use ($n) {
            $data = [
                $row['identification'],
                $row['name'],
                $n($row['subtotal']),
                $n($row['no_iva']),
                $n($row['exempt']),
                $n($row['base0']),
            ];

            if ($this->showNewRates) {
                $data[] = $n($row['base5']);
                $data[] = $n($row['base8']);
            }
            if ($this->showOldRates) {
                $data[] = $n($row['base12']);
            }
            if ($this->showNewRates) {
                $data[] = $n($row['base15']);
                $data[] = $n($row['iva5']);
                $data[] = $n($row['iva8']);
            }
            if ($this->showOldRates) {
                $data[] = $n($row['iva12']);
            }
            if ($this->showNewRates) {
                $data[] = $n($row['iva15']);
            }

            $data[] = $n($row['total']);
            $data[] = $n($row['retentions']);
            $data[] = $n($row['a_pagar']);

            return $data;
        }, $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        $headings = ['RUC / Cédula', 'Proveedor', 'Subtotal', 'No IVA', 'Excenta', 'Base 0%'];

        if ($this->showNewRates) {
            $headings[] = 'Base 5%';
            $headings[] = 'Base 8%';
        }
        if ($this->showOldRates) {
            $headings[] = 'Base 12%';
        }
        if ($this->showNewRates) {
            $headings[] = 'Base 15%';
            $headings[] = 'IVA 5%';
            $headings[] = 'IVA 8%';
        }
        if ($this->showOldRates) {
            $headings[] = 'IVA 12%';
        }
        if ($this->showNewRates) {
            $headings[] = 'IVA 15%';
        }

        $headings[] = 'Total';
        $headings[] = 'Retenciones';
        $headings[] = 'A Pagar';

        return $headings;
    }

    public function title(): string
    {
        return 'Compras por Proveedor';
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return ['A' => NumberFormat::FORMAT_TEXT];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getColumn() === 'A' && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 30];
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
