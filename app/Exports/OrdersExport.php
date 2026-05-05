<?php

namespace App\Exports;

use App\Models\Tenant\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    /** @var array<int, string> */
    private array $columns;

    private Builder $query;

    /** @var array<string, string> */
    public static array $availableColumns = [
        'emision' => 'Emisión',
        'voucher_type' => 'Tipo Comprobante',
        'serie' => 'Serie',
        'contact_identification' => 'RUC / Cédula',
        'contact_name' => 'Cliente',
        'autorization' => 'Autorización',
        'sub_total' => 'Sub Total',
        'no_iva' => 'No IVA',
        'base0' => 'Base 0%',
        'base5' => 'Base 5%',
        'base12' => 'Base 12%',
        'base15' => 'Base 15%',
        'iva5' => 'IVA 5%',
        'iva12' => 'IVA 12%',
        'iva15' => 'IVA 15%',
        'discount' => 'Descuento',
        'ice' => 'ICE',
        'total' => 'Total',
        'state' => 'Estado',
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
    }

    public function collection(): Collection
    {
        return $this->query
            ->with(['contact:id,identification,name', 'voucherType:id,description'])
            ->select('orders.*')
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
        'sub_total', 'no_iva', 'base0', 'base5', 'base12', 'base15',
        'iva5', 'iva12', 'iva15', 'discount', 'ice', 'total',
    ];

    /** @return array<int, mixed> */
    public function map($order): array
    {
        /** @var Order $order */
        $row = [];

        foreach ($this->columns as $col) {
            $row[] = match ($col) {
                'emision' => $order->emision?->format('d-m-Y') ?? '',
                'date_retention' => $order->date_retention?->format('d-m-Y') ?? '',
                'voucher_type' => $order->voucherType?->description ?? '',
                'serie' => ($order->initial ? "{$order->initial}-" : '').($order->serie ?? ''),
                'contact_identification' => $order->contact?->identification ?? '',
                'contact_name' => $order->contact?->name ?? '',
                default => in_array($col, self::NUMERIC_COLUMNS)
                    ? ($order->{$col} ?? 0)
                    : ($order->{$col} ?? ''),
            };
        }

        return $row;
    }

    /** @return array<int|string, mixed> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
