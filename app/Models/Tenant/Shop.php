<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends BaseModel
{
    protected $fillable = [
        'account_id',
        'company_id',
        'voucher_type_id',
        'tax_support_id',
        'contact_id',
        'emision',
        'autorization',
        'autorized_at',
        'serie',
        'sub_total',
        'no_iva',
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

        // ESTOS
        'voucher_type_modify_id',
        'est_modify',
        'poi_modify',
        'sec_modify',
        'aut_modify',
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
        ];
    }

    public function retentionItems(): HasMany
    {
        return $this->hasMany(ShopRetentionItem::class);
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

    public function voucherTypeModify()
    {
        return $this->belongsTo(
            \App\Models\Tenant\VoucherType::class,
            'voucher_type_modify_id'
        );
    }
}
