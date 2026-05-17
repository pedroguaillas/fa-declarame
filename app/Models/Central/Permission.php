<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function modelPermissions(): HasMany
    {
        return $this->hasMany(ModelPermission::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'model_permissions')
            ->withPivot('model_entity_id')
            ->withTimestamps();
    }
}
