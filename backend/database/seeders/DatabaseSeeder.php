<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\AppointmentStateSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AppointmentStateSeeder::class,
            RoleSeeder::class,
            UserTypeSeeder::class,
            AdminActionSeeder::class,
            SuperAdminSeeder::class,
            DoctorSeeder::class,
            SpecialtySeeder::class,
        ]);
    }
}
