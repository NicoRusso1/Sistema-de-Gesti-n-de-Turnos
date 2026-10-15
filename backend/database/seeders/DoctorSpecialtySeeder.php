<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

class DoctorSpecialtySeeder extends Seeder
{
    public function run(): void
    {
        $doctor = Patient::where('email', 'medico@hospital.test')->firstOrFail();

        $doctor->specialties()->syncWithoutDetaching(Specialty::pluck('id')->all());
    }
}