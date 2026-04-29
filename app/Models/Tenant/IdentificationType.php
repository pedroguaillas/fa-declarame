<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdentificationType extends Model
{
    protected $fillable = ['id', 'code_order', 'code_shop', 'description'];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
