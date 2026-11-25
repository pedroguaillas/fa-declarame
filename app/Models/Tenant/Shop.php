<?php

namespace App\Models\Tenant;

use Constants;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends BaseModel
{
    /**
     * Sustentos con crédito tributario de IVA y su equivalente de costo/gasto IR.
     * Una nota de venta (RISE/RIMPE) no otorga crédito tributario.
     *
     * @var array<string, string>
     */
    public const CREDIT_TO_EXPENSE_TAX_SUPPORT = [
        '01' => '02',
        '03' => '04',
        '06' => '07',
    ];

    protected $fillable = [
        'account_id',
        'company_id',
        'voucher_type_id',
        'tax_support_id',
        'contact_id',
        'supplier_type',
        'emision',
        'autorization',
        'autorized_at',
        'serie',
        'sub_total',
        'no_iva',
        'exempt',
        'base0',
        'base5',
        'base8',
        'base12',
        'base15',
        'iva5',
        'iva8',
        'iva12',
        'iva15',
        'aditional_discount',
        'discount',
        'ice',
        'total',
        'state',
        'serie_retention',
        'date_retention',
        'state_retention',
        'autorization_retention',
        'retention_at',

        // N/C - N/D
        'voucher_type_modify_id',
        'est_modify',
        'poi_modify',
        'sec_modify',
        'aut_modify',
        'data_additional',
    ];

    protected function casts(): array
    {
        return [
            'emision' => 'date:d-m-Y',
            'autorized_at' => 'datetime',
            'date_retention' => 'date:d-m-Y',
            'retention_at' => 'datetime',
            'sub_total' => 'decimal:2',
            'no_iva' => 'decimal:2',
            'base0' => 'decimal:2',
            'base5' => 'decimal:2',
            'base8' => 'decimal:2',
            'base12' => 'decimal:2',
            'base15' => 'decimal:2',
            'iva5' => 'decimal:2',
            'iva8' => 'decimal:2',
            'iva12' => 'decimal:2',
            'iva15' => 'decimal:2',
            'aditional_discount' => 'decimal:2',
            'discount' => 'decimal:2',
            'ice' => 'decimal:2',
            'total' => 'decimal:2',
            'data_additional' => 'array',
        ];
    }

    public function retentionItems(): HasMany
    {
        return $this->hasMany(ShopRetentionItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopItem::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function voucherType(): BelongsTo
    {
        return $this->belongsTo(VoucherType::class);
    }

    public function taxSupport(): BelongsTo
    {
        return $this->belongsTo(TaxSupport::class);
    }

    /**
     * Resuelve el código de sustento tributario según el tipo de comprobante.
     *
     * - Nota de venta: convierte sustentos con crédito tributario a su equivalente de costo/gasto.
     * - Al dejar de ser nota de venta: revierte el equivalente de costo/gasto a crédito tributario.
     * - Otros casos: conserva el sustento actual o usa 01 por defecto.
     */
    public static function taxSupportCodeFor(
        string $voucherCode,
        ?string $currentTaxSupportCode = null,
        ?string $previousVoucherCode = null,
    ): string {
        if ($voucherCode === Constants::NOTA_VENTA) {
            if ($currentTaxSupportCode === null) {
                return '02';
            }

            return self::CREDIT_TO_EXPENSE_TAX_SUPPORT[$currentTaxSupportCode] ?? $currentTaxSupportCode;
        }

        if ($currentTaxSupportCode === null) {
            return '01';
        }

        if ($previousVoucherCode === Constants::NOTA_VENTA) {
            $expenseToCredit = array_flip(self::CREDIT_TO_EXPENSE_TAX_SUPPORT);

            return $expenseToCredit[$currentTaxSupportCode] ?? $currentTaxSupportCode;
        }

        return $currentTaxSupportCode;
    }

    public function voucherTypeModify()
    {
        return $this->belongsTo(
            VoucherType::class,
            'voucher_type_modify_id'
        );
    }
}
