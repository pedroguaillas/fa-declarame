<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelPermission extends Model
{
    protected $fillable = ['role_id', 'permission_id', 'model_entity_id'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function modelEntity(): BelongsTo
    {
        return $this->belongsTo(ModelEntity::class);
    }
}