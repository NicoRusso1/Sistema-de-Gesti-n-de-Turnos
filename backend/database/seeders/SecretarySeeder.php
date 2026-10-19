<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserPermission;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SecretarySeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $secretaryType = UserType::where('name', UserTypeName::Secretary->value)->firstOrFail();

        $secretary = Patient::firstOrCreate(
            ['email' => 'secretaria@hospital.test'],
            [
                'first_name' => 'Laura',
                'last_name' => 'Fernández',
                'password_hash' => Hash::make('password'),
                'role_id' => $role->id,
                'user_type_id' => $secretaryType->id,
                'status' => 1,
                'email_verified_at' => now(),
            ]
        );

        if ($secretary->status !== 1) {
            $secretary->update(['status' => 1]);
        }

        // Mismos permisos por defecto que recibe una secretaria creada desde Gestión de Usuarios
        UserPermission::firstOrCreate(
            ['patient_id' => $secretary->id],
            UserPermission::defaultsFor(UserTypeName::Secretary->value)
        );
    }
}
