<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelEntity extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function modelPermissions(): HasMany
    {
        return $this->hasMany(ModelPermission::class);
    }
}
