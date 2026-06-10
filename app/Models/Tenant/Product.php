<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'contact_id',
        'code',
        'aux_code',
        'description',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function shopItems(): HasMany
    {
        return $this->hasMany(ShopItem::class);
    }

    public function productAccounts(): HasMany
    {
        return $this->hasMany(ProductAccount::class);
    }
}
