<?php

namespace Database\Seeders;

use App\Enums\AdminActionName;
use App\Models\AdminAction;
use App\Models\AdminActionLog;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class DemoAuditoriaSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Patient::where('email', 'superadmin@hospital.test')->firstOrFail();

        $registros = [
            [13, '10:20', 'admin@hospital.test', AdminActionName::GrantOwner],
            [11, '15:05', 'gabriela.dominguez@hospital.test', AdminActionName::GrantAdmin],
            [9, '09:40', 'marcela.ibarra@hospital.test', AdminActionName::GrantAdmin],
            [8, '17:15', 'valentina.romero@hospital.test', AdminActionName::Deactivate],
            [8, '18:00', 'valentina.romero@hospital.test', AdminActionName::Activate],
            [6, '16:30', 'tomas.herrera@hospital.test', AdminActionName::Deactivate],
            [5, '11:10', 'tomas.herrera@hospital.test', AdminActionName::Activate],
            [3, '14:25', 'marcela.ibarra@hospital.test', AdminActionName::RevokeAdmin],
        ];

        $acciones = AdminAction::pluck('id', 'name');

        foreach ($registros as [$dias, $hora, $email, $accion]) {
            $afectado = Patient::where('email', $email)->firstOrFail();

            $existe = AdminActionLog::where('superadmin_id', $superAdmin->id)
                ->where('affected_admin_id', $afectado->id)
                ->where('action_id', $acciones[$accion->value])
                ->exists();

            if ($existe) {
                continue;
            }

            AdminActionLog::create([
                'superadmin_id' => $superAdmin->id,
                'affected_admin_id' => $afectado->id,
                'action_id' => $acciones[$accion->value],
                'date' => now()->subDays($dias)->setTimeFromTimeString($hora),
            ]);
        }
    }
}
