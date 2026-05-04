<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopsByRetentionExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['code'],
            $row['description'],
            $row['percentage'].'%',
            $row['base'],
            $row['value'],
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Código', 'Descripción', 'Retenido %', 'Base', 'Valor'];
    }

    public function title(): string
    {
        return 'Compras por Retenciones';
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
