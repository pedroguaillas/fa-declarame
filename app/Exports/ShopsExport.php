<?php

namespace App\Exports;

use App\Models\Tenant\Shop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ShopsExport implements FromCollection, WithHeadings, WithMapping
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
        'contact_name' => 'Proveedor',
        'autorization' => 'Autorización',
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

    /** @return array<int, mixed> */
    public function map($shop): array
    {
        /** @var Shop $shop */
        $row = [];

        foreach ($this->columns as $col) {
            $row[] = match ($col) {
                'voucher_type' => $shop->voucherType?->description ?? '',
                'contact_identification' => $shop->contact?->identification ?? '',
                'contact_name' => $shop->contact?->name ?? '',
                'account' => $shop->account
                    ? "{$shop->account->code} – {$shop->account->name}"
                    : '',
                default => $shop->{$col} ?? '',
            };
        }

        return $row;
    }
}
