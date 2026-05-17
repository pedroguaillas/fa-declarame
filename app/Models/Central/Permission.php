<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    protected $fillable = ['model_entity_id', 'name', 'slug', 'description'];

    public function modelEntity(): BelongsTo
    {
        return $this->belongsTo(ModelEntity::class);
    }
}
