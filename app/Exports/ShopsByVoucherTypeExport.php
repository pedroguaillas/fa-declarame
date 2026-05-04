<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopsByVoucherTypeExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['code'],
            $row['description'],
            $row['count'],
            $row['subtotal'],
            $row['iva'],
            $row['total'],
            $row['retentions'],
            $row['a_pagar'],
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Código', 'Tipo de Comprobante', 'Cantidad', 'Subtotal', 'IVA', 'Total', 'Retenciones', 'A Pagar'];
    }

    public function title(): string
    {
        return 'Compras por Tipo de Comprobante';
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
