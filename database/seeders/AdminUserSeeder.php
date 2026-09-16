<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea el administrador inicial del sistema.
     *
     * Es necesario que exista al menos un Administrador desde el arranque,
     * ya que es el unico rol habilitado para dar de alta Medicos.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@hospital.test'],
            [
                'name' => 'Administrador General',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole(RoleName::Administrador->value);
    }
}
