<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PlanSeeder::class,
            PermissionSeeder::class,
            ModelEntitySeeder::class,
            ModelPermissionSeeder::class,
            TenantSeeder::class,  
            UserSeeder::class,
            SubscriptionSeeder::class,
        ]);
    }
}