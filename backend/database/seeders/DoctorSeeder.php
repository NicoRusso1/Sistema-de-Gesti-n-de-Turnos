<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $doctorType = UserType::where('name', UserTypeName::Doctor->value)->firstOrFail();

        $doctor = Patient::firstOrCreate(
            ['email' => 'medico@hospital.test'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Pérez',
                'password_hash' => Hash::make('password'),
                'role_id' => $role->id,
                'user_type_id' => $doctorType->id,
                'status' => 1,
                'email_verified_at' => now(),
            ]
        );

        if ($doctor->status !== 1) {
            $doctor->update(['status' => 1]);
        }
    }
}
