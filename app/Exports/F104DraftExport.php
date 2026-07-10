<?php

namespace App\Exports;

use App\Exports\DeclarationForm\FormSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class F104DraftExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{section: string, rows: array<int, array{c: string, d: string, v: float|int|string|null, t: string}>}>  $sections
     * @param  array<int, array{code: string, base: float, value: float}>  $unmapped
     */
    public function __construct(
        private readonly array $sections,
        private readonly array $unmapped,
        private readonly string $periodLabel,
        private readonly string $ruc,
        private readonly ?string $companyName,
    ) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            new FormSheet(
                'F104',
                'FORMULARIO 104 — DECLARACIÓN DEL IMPUESTO AL VALOR AGREGADO (IVA)',
                $this->sections,
                $this->periodLabel,
                $this->ruc,
                $this->companyName,
                $this->unmapped,
            ),
        ];
    }
}
