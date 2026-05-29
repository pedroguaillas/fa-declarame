<?php

namespace App\Exports;

use Constants;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopsByAccountExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
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
                ($row['account_code'] ? $row['account_code'].' – ' : '').$row['account_name'],
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
        $headings = ['Cuenta Contable', 'Subtotal', 'No IVA', 'Excenta', 'Base 0%'];

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

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 35];
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
