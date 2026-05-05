<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersByClientExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['identification'],
            $row['name'],
            $row['subtotal'] ?? 0,
            $row['iva'] ?? 0,
            $row['total'] ?? 0,
            $row['retentions'] ?? 0,
            $row['a_cobrar'] ?? 0,
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['RUC / Cédula', 'Cliente', 'Subtotal', 'IVA', 'Total', 'Retenciones', 'A Cobrar'];
    }

    public function title(): string
    {
        return 'Ventas por Cliente';
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
