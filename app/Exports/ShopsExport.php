<?php

namespace App\Exports;

use App\Models\Tenant\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShopsExport extends DefaultValueBinder implements FromCollection, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles
{
    /** @var array<int, string> */
    private array $columns;

    private Builder $query;

    private ?string $identificationColumn = null;

    /** @var array<string, string> */
    public static array $availableColumns = [
        'emision' => 'Emisión',
        'voucher_type' => 'Tipo Comprobante',
        'serie' => 'Serie',
        'contact_identification' => 'RUC / Cédula',
        'contact_name' => 'Proveedor',
        'autorization' => 'Autorización',
        'exempt' => 'Excenta',
        'sub_total' => 'Sub Total',
        'no_iva' => 'No IVA',
        'base0' => 'Base 0%',
        'base5' => 'Base 5%',
        'base8' => 'Base 8%',
        'base12' => 'Base 12%',
        'base15' => 'Base 15%',
        'iva5' => 'IVA 5%',
        'iva8' => 'IVA 8%',
        'iva12' => 'IVA 12%',
        'iva15' => 'IVA 15%',
        'discount' => 'Descuento',
        'ice' => 'ICE',
        'total' => 'Total',
        'state' => 'Estado',
        'account' => 'Cuenta Contable',
        'serie_retention' => 'Serie Retención',
        'date_retention' => 'Fecha Retención',
        'state_retention' => 'Estado Retención',
        'autorization_retention' => 'Autorización Retención',
    ];

    /** @param array<int, string> $columns */
    public function __construct(Builder $query, array $columns)
    {
        $this->query = $query;
        $this->columns = $columns;

        $index = array_search('contact_identification', $columns);
        if ($index !== false) {
            $this->identificationColumn = Coordinate::stringFromColumnIndex((int) $index + 1);
        }
    }

    public function collection(): Collection
    {
        return $this->query
            ->with(['contact:id,identification,name', 'account:id,code,name', 'voucherType:id,description'])
            ->select('shops.*')
            ->orderBy('emision')
            ->get();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_map(
            fn (string $col) => self::$availableColumns[$col] ?? $col,
            $this->columns,
        );
    }

    /** @var array<int, string> */
    private const NUMERIC_COLUMNS = [
        'exempt', 'sub_total', 'no_iva', 'base0', 'base5', 'base8', 'base12', 'base15',
        'iva5', 'iva8', 'iva12', 'iva15', 'discount', 'ice', 'total',
    ];

    /** @var array<string, int> */
    private const COLUMN_WIDTHS = [
        'emision' => 10,
        'serie' => 18,
        'contact_identification' => 14,
        'contact_name' => 30,
    ];

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        $widths = [];
        foreach ($this->columns as $index => $key) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $widths[$letter] = self::COLUMN_WIDTHS[$key] ?? 8;
        }

        return $widths;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        $formats = [];

        foreach ($this->columns as $index => $key) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            if ($key === 'contact_identification') {
                $formats[$letter] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formats;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($this->identificationColumn !== null && $cell->getColumn() === $this->identificationColumn && $cell->getRow() > 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /** @return array<int, mixed> */
    public function map($shop): array
    {
        /** @var Shop $shop */
        $row = [];

        foreach ($this->columns as $col) {
            $row[] = match ($col) {
                'emision' => $shop->emision?->format('d-m-Y') ?? '',
                'date_retention' => $shop->date_retention?->format('d-m-Y') ?? '',
                'voucher_type' => $shop->voucherType?->description ?? '',
                'contact_identification' => $shop->contact?->identification ?? '',
                'contact_name' => $shop->contact?->name ?? '',
                'account' => $shop->account
                    ? "{$shop->account->code} – {$shop->account->name}"
                    : '',
                default => in_array($col, self::NUMERIC_COLUMNS)
                    ? (float) ($shop->{$col} ?? 0)
                    : ($shop->{$col} ?? ''),
            };
        }

        return $row;
    }
}
