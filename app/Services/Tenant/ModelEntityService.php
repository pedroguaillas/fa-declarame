<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ModelEntity;

class ModelEntityService
{
    public function all()
    {
        return ModelEntity::all();
    }

    public function allWithPermissions()
    {
        return ModelEntity::with('permissions')->get();
    }

    public function create(array $data): ModelEntity
    {
        $entity = ModelEntity::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        if (isset($data['permissions'])) {
            $entity->permissions()->createMany(
                collect($data['permissions'])->map(fn ($p) => [
                    'name' => $p['name'],
                    'slug' => $p['slug'],
                ])->toArray(),
            );
        }

        return $entity->load('permissions');
    }

    public function update(ModelEntity $entity, array $data): ModelEntity
    {
        $entity->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        if (array_key_exists('permissions', $data)) {
            if ($data['permissions'] === null) {
                return $entity->load('permissions');
            }

            $entity->permissions()->delete();

            if (! empty($data['permissions'])) {
                $entity->permissions()->createMany(
                    collect($data['permissions'])->map(fn ($p) => [
                        'name' => $p['name'],
                        'slug' => $p['slug'],
                    ])->toArray(),
                );
            }
        }

        return $entity->load('permissions');
    }
}
