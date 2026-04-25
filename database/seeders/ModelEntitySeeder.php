<?php

namespace Database\Seeders;

use App\Models\ModelEntity;
use Illuminate\Database\Seeder;

class ModelEntitySeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'name' => 'Permisos',
                'slug' => 'permissions',
                'description' => 'Gestión de permisos',
            ],
            [
                'name' => 'Modelos',
                'slug' => 'models',
                'description' => 'Gestión de modelos del sistema',
            ],
            [
                'name' => 'Roles',
                'slug' => 'roles',
                'description' => 'Gestión de roles',
            ],
            [
                'name' => 'Usuarios',
                'slug' => 'users',
                'description' => 'Gestión de usuarios',
            ],
            [
                'name' => 'Planes',
                'slug' => 'plans',
                'description' => 'Gestión de planes',
            ],
            [
                'name' => 'Suscripciones',
                'slug' => 'subscriptions',
                'description' => 'Gestión de suscripciones',
            ],
        ];

        foreach ($models as $model) {
            ModelEntity::updateOrCreate(['slug' => $model['slug']], $model);
        }
    }
}