<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $patientType = UserType::where('name', UserTypeName::Patient->value)->firstOrFail();

        Patient::firstOrCreate(
            ['email' => 'paciente@hospital.test'],
            [
                'first_name' => 'María',
                'last_name' => 'González',
                'password_hash' => Hash::make('password'),
                'role_id' => $role->id,
                'user_type_id' => $patientType->id,
                'status' => 1,
                'email_verified_at' => now(),
            ]
        );
    }
}