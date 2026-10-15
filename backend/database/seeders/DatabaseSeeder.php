<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserTypeSeeder::class,
            AdminActionSeeder::class,
            SuperAdminSeeder::class,
            AdministratorSeeder::class,
            DoctorSeeder::class,             
            PatientSeeder::class,
            SpecialtySeeder::class,
            SpecialtySeeder::class,
            DoctorSpecialtySeeder::class,
            HealthInsuranceSeeder::class,
            HealthInsuranceSeeder::class,
            DoctorScheduleSeeder::class,
            PaymentMethodSeeder::class,
            PaymentStatusSeeder::class,
        ]);
    }
}
