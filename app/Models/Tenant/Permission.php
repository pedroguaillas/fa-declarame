<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'model_entity_id'];

    public function modelEntity(): BelongsTo
    {
        return $this->belongsTo(ModelEntity::class);
    }
}
