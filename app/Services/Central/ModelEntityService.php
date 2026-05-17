<?php

namespace App\Services\Central;

use App\Models\Central\ModelEntity;

class ModelEntityService
{
    public function all()
    {
        return ModelEntity::all();
    }

    public function allWithCount()
    {
        return ModelEntity::withCount('modelPermissions')->get();
    }

    public function create(array $data): ModelEntity
    {
        return ModelEntity::create($data);
    }

    public function update(ModelEntity $modelEntity, array $data): ModelEntity
    {
        $modelEntity->update($data);

        return $modelEntity;
    }
}
