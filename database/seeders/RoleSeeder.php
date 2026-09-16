<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Crea los roles jerarquicos del sistema: Usuario, Medico y Administrador.
     */
    public function run(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value]);
        }
    }
}
