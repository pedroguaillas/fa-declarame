<?php

namespace App\Services;

use App\Models\Tenant\Contact;
use App\Models\Tenant\IdentificationType;
use App\Models\Tenant\Order;
use App\Models\Tenant\Scopes\CompanyScope;
use App\Models\Tenant\VoucherType;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SalesXlsxImportService
{
    // Column indices (0-based) — add new columns at the end and increment EXPECTED_COLUMNS
    public const COL_EMISION = 0;

    public const COL_TIPO_COMPROBANTE = 1;

    public const COL_SERIE = 2;

    public const COL_IDENTIFICACION = 3;

    public const COL_CLIENTE = 4;

    public const COL_AUTORIZACION = 5;

    public const COL_EXCENTA = 6;

    public const COL_NO_IVA = 7;

    public const COL_BASE0 = 8;

    public const COL_BASE5 = 9;

    public const COL_BASE15 = 10;

    public const COL_IVA5 = 11;

    public const COL_IVA15 = 12;

    public const COL_TOTAL = 13;

    public const EXPECTED_COLUMNS = 14;

    private const VOUCHER_TYPE_MAP = [
        'factura' => \Constants::FACTURA,
        'nota de credito' => \Constants::NOTA_CREDITO,
        'nota de crédito' => \Constants::NOTA_CREDITO,
        'nota de debito' => \Constants::NOTA_DEBITO,
        'nota de débito' => \Constants::NOTA_DEBITO,
        'nota de venta' => \Constants::NOTA_VENTA,
    ];

    /** @var array<string, int> */
    private array $voucherTypeCache = [];

    /** @var array<string, int> */
    private array $identificationTypeCache = [];

    public function __construct(
        private readonly SriResolveNameService $sriResolver,
    ) {}

    /**
     * @return array{imported: int, skipped: int, errors: int, errorMessages: string[]}
     */
    public function import(string $filePath, int $companyId, string $companyRuc): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $errorMessages = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $lineNum = $index + 2;

            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            try {
                $result = $this->processRow($row, $companyId, $companyRuc);
                if ($result === 'imported') {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $errorMessages[] = "Fila {$lineNum}: {$e->getMessage()}";
            }
        }

        return compact('imported', 'skipped', 'errors', 'errorMessages');
    }

    private function processRow(array $row, int $companyId, string $companyRuc): string
    {
        $emision = $this->parseDate($row[self::COL_EMISION] ?? null);
        $voucherCode = $this->parseVoucherType($row[self::COL_TIPO_COMPROBANTE] ?? null);
        $serie = $this->parseSerie($row[self::COL_SERIE] ?? null);
        $identification = $this->parseIdentification($row[self::COL_IDENTIFICACION] ?? null);
        $autorizacion = trim((string) ($row[self::COL_AUTORIZACION] ?? ''));
        $clienteName = trim((string) ($row[self::COL_CLIENTE] ?? ''));
        $amounts = $this->parseAmounts($row, $voucherCode);

        if (strlen($autorizacion) === 49 && substr($autorizacion, 10, 13) !== $companyRuc) {
            throw new \InvalidArgumentException(
                "Autorización '{$autorizacion}': el RUC embebido no corresponde al contribuyente seleccionado."
            );
        }

        if ($this->isDuplicate($companyId, $autorizacion, $serie)) {
            return 'skipped';
        }

        $contactId = $this->resolveContact($identification, $clienteName);
        $voucherTypeId = $this->getVoucherTypeId($voucherCode);

        Order::create([
            'company_id' => $companyId,
            'contact_id' => $contactId,
            'voucher_type_id' => $voucherTypeId,
            'emision' => $emision,
            'autorization' => $autorizacion !== '' ? $autorizacion : null,
            'serie' => $serie,
            'state' => 'AUTORIZADO',
            ...$amounts,
        ]);

        return 'imported';
    }

    private function parseDate(mixed $value): string
    {
        $date = trim((string) $value);

        if (! preg_match('/^\d{2}-\d{2}-\d{4}$/', $date)) {
            throw new \InvalidArgumentException("Fecha inválida '{$date}'. Formato esperado: DD-MM-YYYY.");
        }

        return Carbon::createFromFormat('d-m-Y', $date)->format('Y-m-d');
    }

    private function parseVoucherType(mixed $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));

        if (! array_key_exists($normalized, self::VOUCHER_TYPE_MAP)) {
            throw new \InvalidArgumentException(
                "Tipo de comprobante inválido: '{$value}'. Permitidos: Factura, Nota de credito, Nota de debito, Nota de venta."
            );
        }

        return self::VOUCHER_TYPE_MAP[$normalized];
    }

    private function parseSerie(mixed $value): string
    {
        $serie = trim((string) $value);

        if (! preg_match('/^\d{3}-\d{3}-\d{9}$/', $serie)) {
            throw new \InvalidArgumentException("Serie inválida: '{$serie}'. Formato esperado: NNN-NNN-NNNNNNNNN.");
        }

        return $serie;
    }

    private function parseIdentification(mixed $value): string
    {
        $identification = trim((string) $value);

        if ($identification === '') {
            throw new \InvalidArgumentException('Identificación vacía.');
        }

        return $identification;
    }

    /**
     * @return array<string, float>
     */
    private function parseAmounts(array $row, string $voucherCode): array
    {
        $noIva = $this->parseDecimal($row[self::COL_NO_IVA] ?? null, 'No IVA');
        $base0 = $this->parseDecimal($row[self::COL_BASE0] ?? null, 'Base 0%');
        $base5 = $this->parseDecimal($row[self::COL_BASE5] ?? null, 'Base 5%');
        $base15 = $this->parseDecimal($row[self::COL_BASE15] ?? null, 'Base 15%');
        $iva5 = $this->parseDecimal($row[self::COL_IVA5] ?? null, 'IVA 5%');
        $iva15 = $this->parseDecimal($row[self::COL_IVA15] ?? null, 'IVA 15%');
        $total = $this->parseDecimal($row[self::COL_TOTAL] ?? null, 'Total');

        if ($voucherCode === \Constants::NOTA_VENTA) {
            $forbidden = array_filter([
                'No IVA' => $noIva,
                'Base 5%' => $base5,
                'Base 15%' => $base15,
                'IVA 5%' => $iva5,
                'IVA 15%' => $iva15,
            ]);

            if (! empty($forbidden)) {
                throw new \InvalidArgumentException(
                    'Nota de Venta solo puede tener valores en Base 0% y Total. Columnas con valor: '.implode(', ', array_keys($forbidden)).'.'
                );
            }
        }

        return [
            'no_iva' => $noIva,
            'base0' => $base0,
            'base5' => $base5,
            'base15' => $base15,
            'iva5' => $iva5,
            'iva15' => $iva15,
            'sub_total' => $base0 + $base5 + $base15 + $noIva,
            'total' => $total,
        ];
    }

    private function parseDecimal(mixed $value, string $label): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Valor no numérico en columna {$label}: '{$value}'.");
        }

        return (float) $value;
    }

    private function isDuplicate(int $companyId, string $autorizacion, string $serie): bool
    {
        $query = Order::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('autorization', $autorizacion !== '' ? $autorizacion : null);

        // 49-char electronic: authorization alone identifies the document
        if (strlen($autorizacion) === 49) {
            return $query->exists();
        }

        // Manual: same authorization + serie must be unique per company
        return $query->where('serie', $serie)->exists();
    }

    private function resolveContact(string $identification, string $nameFromFile): int
    {
        $existing = Contact::where('identification', $identification)->first();

        if ($existing) {
            return $existing->id;
        }

        $identLen = strlen($identification);
        $isNumeric = ctype_digit($identification);

        if ($identLen === 13 && $isNumeric) {
            $typeCode = \Constants::RUC_VENTA;
            try {
                $sri = $this->sriResolver->searchByIdentificationSRI($identification);
                $name = $sri['name'];
            } catch (\Throwable) {
                $name = $nameFromFile;
            }
        } elseif ($identLen === 10 && $isNumeric) {
            $typeCode = \Constants::CEDULA_VENTA;
            $name = $nameFromFile;
        } else {
            $typeCode = \Constants::PASAPORTE_VENTA;
            $name = $nameFromFile;
        }

        if ($name === '') {
            throw new \InvalidArgumentException("Sin nombre para identificación '{$identification}'.");
        }

        return Contact::create([
            'identification_type_id' => $this->getIdentificationTypeId($typeCode),
            'identification' => $identification,
            'name' => $name,
        ])->id;
    }

    private function getVoucherTypeId(string $code): int
    {
        return $this->voucherTypeCache[$code] ??= (int) VoucherType::where('code', $code)->value('id');
    }

    private function getIdentificationTypeId(string $codeOrder): int
    {
        return $this->identificationTypeCache[$codeOrder] ??= (int) IdentificationType::where('code_order', $codeOrder)->value('id');
    }
}
