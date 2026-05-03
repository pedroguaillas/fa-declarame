<?php

namespace App\Imports;

use App\Models\Tenant\Account;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class AccountsImport implements ToCollection, WithStartRow
{
    /**
     * @var array<string, string>
     */
    private static array $typeMap = [
        '1' => 'activo',
        '2' => 'pasivo',
        '3' => 'patrimonio',
        '4' => 'ingreso',
        '5' => 'costo',
    ];

    public function startRow(): int
    {
        return 4;
    }

    public function collection(Collection $rows): void
    {
        /** @var array<int, array{code: string, name: string}> $data */
        $data = $rows
            ->filter(fn ($row) => ! empty($row[0]) && ! empty($row[1]))
            ->map(fn ($row) => [
                'code' => (string) $row[0],
                'name' => trim((string) $row[1]),
            ])
            ->values()
            ->toArray();

        $allCodes = array_column($data, 'code');

        /** @var array<string, int> $codeToId */
        $codeToId = [];

        foreach ($data as $item) {
            $code = $item['code'];
            $parentId = $this->resolveParentId($code, $codeToId);
            $type = $this->resolveType($code);
            $isDetail = $this->resolveIsDetail($code, $allCodes);

            $account = Account::create([
                'code' => $code,
                'name' => $item['name'],
                'type' => $type,
                'is_detail' => $isDetail,
                'parent_id' => $parentId,
            ]);

            $codeToId[$code] = $account->id;
        }
    }

    /**
     * @param  array<string, int>  $codeToId
     */
    private function resolveParentId(string $code, array $codeToId): ?int
    {
        $segments = explode('.', $code);

        if (count($segments) <= 1) {
            return null;
        }

        array_pop($segments);
        $parentCode = implode('.', $segments);

        return $codeToId[$parentCode] ?? null;
    }

    private function resolveType(string $code): string
    {
        $firstDigit = $code[0];

        return self::$typeMap[$firstDigit] ?? 'activo';
    }

    /**
     * @param  array<int, string>  $allCodes
     */
    private function resolveIsDetail(string $code, array $allCodes): bool
    {
        foreach ($allCodes as $other) {
            if ($other !== $code && str_starts_with($other, $code.'.')) {
                return false;
            }
        }

        return true;
    }
}
