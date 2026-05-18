<?php

namespace App\Services\Central;

use App\Models\Central\ModelEntity;

class ModelEntityService
{
    public function all()
    {
        return ModelEntity::all();
    }

    public function allWithPermissions()
    {
        return ModelEntity::with('permissions')
            ->withCount('modelPermissions')
            ->get();
    }

    public function create(array $data): ModelEntity
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $entity = ModelEntity::create($data);

        if (! empty($permissions)) {
            $entity->permissions()->createMany($permissions);
        }

        return $entity;
    }

    public function update(ModelEntity $modelEntity, array $data): ModelEntity
    {
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        $modelEntity->update($data);

        if ($permissions !== null) {
            $modelEntity->permissions()->delete();
            $modelEntity->permissions()->createMany($permissions);
        }

        return $modelEntity;
    }
}
