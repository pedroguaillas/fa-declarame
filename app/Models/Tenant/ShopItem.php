<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopItem extends Model
{
    protected $fillable = [
        'shop_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount',
        'total',
        'tax_percentage',
        'tax_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity'       => 'decimal:4',
            'unit_price'     => 'decimal:6',
            'discount'       => 'decimal:2',
            'total'          => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax_value'      => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
