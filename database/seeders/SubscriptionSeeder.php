<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $admin      = User::where('email', 'info@facec.ec')->first();
        $superAdmin = User::where('email', 'peter.tufi@gmail.com')->first();
        $proPlan    = Plan::where('slug', 'pro')->first();

        if (!$admin || !$superAdmin || !$proPlan) return;

        Subscription::updateOrCreate(
            ['user_id' => $admin->id, 'plan_id' => $proPlan->id],
            [
                'created_by' => $superAdmin->id,
                'start_date' => now(),
                'end_date'   => now()->addMonths(1),
                'is_active'  => true,
                'notes'      => 'Suscripción inicial de prueba',
            ]
        );
    }
}
