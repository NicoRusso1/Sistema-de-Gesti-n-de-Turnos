<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', RoleName::Administrator->value)->firstOrFail();

        Patient::firstOrCreate(
            ['email' => 'admin@hospital.test'],
            [
                'first_name' => 'Administrador',
                'last_name' => 'Propietario',
                'password_hash' => Hash::make('password'),
                'role_id' => $role->id,
                'status' => 1,
                'is_owner' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}