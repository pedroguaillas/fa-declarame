<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopsByAccountExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private readonly array $rows) {}

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return array_map(fn (array $row) => [
            ($row['account_code'] ? $row['account_code'].' – ' : '').$row['account_name'],
            $row['subtotal'] ?? 0,
            $row['iva'] ?? 0,
            $row['total'] ?? 0,
            $row['retentions'] ?? 0,
            $row['a_pagar'] ?? 0,
        ], $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Cuenta Contable', 'Subtotal', 'IVA', 'Total', 'Retenciones', 'A Pagar'];
    }

    public function title(): string
    {
        return 'Compras por Cuentas';
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
