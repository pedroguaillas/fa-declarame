<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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