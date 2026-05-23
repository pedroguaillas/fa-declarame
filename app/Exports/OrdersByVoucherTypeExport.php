<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersByVoucherTypeExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['description'],
            (int) ($row['count'] ?? 0),
            (float) ($row['subtotal'] ?? 0),
            (float) ($row['iva'] ?? 0),
            (float) ($row['total'] ?? 0),
            (float) ($row['retentions'] ?? 0),
            (float) ($row['a_cobrar'] ?? 0),
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Tipo de Comprobante', 'Cantidad', 'Subtotal', 'IVA', 'Total', 'Retenciones', 'A Cobrar'];
    }

    public function title(): string
    {
        return 'Ventas por Tipo de Comprobante';
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 25];
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
