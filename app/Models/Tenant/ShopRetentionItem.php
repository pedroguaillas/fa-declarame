<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopRetentionItem extends Model
{
    protected $fillable = [
        'shop_id',
        'retention_id',
        'base',
        'percentage',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'base' => 'decimal:2',
            'percentage' => 'decimal:2',
            'value' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function retention(): BelongsTo
    {
        return $this->belongsTo(Retention::class);
    }
}
