<?php

namespace Database\Seeders;

use App\Models\AppointmentState;
use Illuminate\Database\Seeder;

class AppointmentStateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['reserved', 'paid', 'waiting', 'cancelled', 'completed'] as $state) {
            AppointmentState::firstOrCreate(['name' => $state]);
        }
    }
}
