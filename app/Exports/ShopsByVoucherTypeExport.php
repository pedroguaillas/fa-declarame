<?php

namespace App\Exports;

use Constants;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopsByVoucherTypeExport implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    /** @var array<string, string> */
    private array $activeColumns;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array{start_date?: string|null, end_date?: string|null}  $filters
     */
    public function __construct(private readonly array $rows, array $filters = [])
    {
        $endDate = $filters['end_date'] ?? null;
        $startDate = $filters['start_date'] ?? null;

        $showNewRates = $endDate === null || $endDate >= Constants::IVA_NEW_RATES_START;
        $showOldRates = $startDate === null || $startDate < Constants::IVA_NEW_RATES_START;

        $candidates = [
            'description' => 'Tipo de Comprobante',
            'count' => 'Cantidad',
            'subtotal' => 'Subtotal',
            'no_iva' => 'No IVA',
            'exempt' => 'Excenta',
            'base0' => 'Base 0%',
        ];

        if ($showNewRates) {
            $candidates['base5'] = 'Base 5%';
            $candidates['base8'] = 'Base 8%';
        }
        if ($showOldRates) {
            $candidates['base12'] = 'Base 12%';
        }
        if ($showNewRates) {
            $candidates['base15'] = 'Base 15%';
            $candidates['iva5'] = 'IVA 5%';
            $candidates['iva8'] = 'IVA 8%';
        }
        if ($showOldRates) {
            $candidates['iva12'] = 'IVA 12%';
        }
        if ($showNewRates) {
            $candidates['iva15'] = 'IVA 15%';
        }

        $candidates['total'] = 'Total';
        $candidates['retentions'] = 'Retenciones';
        $candidates['a_pagar'] = 'A Pagar';

        // Keep non-numeric columns always; drop numeric columns where all values are zero
        $nonNumeric = ['description', 'count'];
        $this->activeColumns = array_filter(
            $candidates,
            fn (string $label, string $key) => in_array($key, $nonNumeric)
                || collect($this->rows)->contains(fn ($r) => (float) ($r[$key] ?? 0) !== 0.0),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $n = fn (mixed $v): float => (float) ($v ?? 0);

        return array_map(function (array $row) use ($n) {
            return array_map(
                fn (string $key) => $key === 'count' ? (int) ($row[$key] ?? 0) : ($key === 'description' ? ($row[$key] ?? '') : $n($row[$key])),
                array_keys($this->activeColumns),
            );
        }, $this->rows);
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_values($this->activeColumns);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return ['A' => 20];
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
