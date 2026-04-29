<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new CompanyScope);
    }
}
