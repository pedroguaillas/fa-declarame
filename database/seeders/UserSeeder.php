<?php

namespace Database\Seeders;

use App\Models\Central\Role;
use App\Models\Central\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::where('slug', 'super_admin')->first();

        // Super Admin de todo el sistema
        User::create([
            'email' => 'peter.tufi@gmail.com',
            'username' => 'peters',
            'name' => 'Pedro Guaillas',
            'password' => 'password',
            'role_id' => $superAdminRole->id,
            'tenant_id' => null,
            'admin_id' => null,
            'is_active' => true,
        ]);
    }
}
