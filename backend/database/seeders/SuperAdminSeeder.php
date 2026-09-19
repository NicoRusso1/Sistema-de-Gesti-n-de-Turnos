<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', RoleName::SuperAdmin->value)->firstOrFail();

        Patient::firstOrCreate(
            ['email' => 'superadmin@hospital.test'],
            [
                'first_name' => 'Super',
                'last_name' => 'Administrador',
                'password_hash' => Hash::make('password'),
                'role_id' => $role->id,
                'status' => null,
                'is_owner' => false,
                'email_verified_at' => now(),
            ]
        );
    }
}