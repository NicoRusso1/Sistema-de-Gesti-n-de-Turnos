<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\DoctorSchedule;
use App\Models\HealthInsurance;
use App\Models\Patient;
use App\Models\PatientHealthInsurance;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserPermission;
use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $this->sembrarMedicos();
        $this->sembrarPacientes();
        $this->sembrarPersonalAdministrativo();
    }

    private function sembrarMedicos(): void
    {
        $medicos = [
            [
                'email' => 'carolina.benitez@hospital.test',
                'first_name' => 'Carolina',
                'last_name' => 'Benítez',
                'phone' => '11 5524-1180',
                'especialidades' => ['Pediatria'],
                'franjas' => [
                    [[1, 2, 3, 4, 5], '08:00', '12:00'],
                ],
            ],
            [
                'email' => 'lucia.marchetti@hospital.test',
                'first_name' => 'Lucía',
                'last_name' => 'Marchetti',
                'phone' => '11 4872-3391',
                'especialidades' => ['Ginecología'],
                'franjas' => [
                    [[2, 4], '09:00', '13:00'],
                    [[1, 3, 5], '14:00', '18:00'],
                ],
            ],
            [
                'email' => 'martin.acosta@hospital.test',
                'first_name' => 'Martín',
                'last_name' => 'Acosta',
                'phone' => '11 6310-7745',
                'especialidades' => ['Clínica Médica'],
                'franjas' => [
                    [[1, 2, 3, 4, 5], '10:00', '14:00'],
                    [[2, 4], '16:00', '19:00'],
                ],
            ],
        ];

        $rol = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $tipo = UserType::where('name', UserTypeName::Doctor->value)->firstOrFail();

        foreach ($medicos as $datos) {
            $medico = Patient::firstOrCreate(
                ['email' => $datos['email']],
                [
                    'first_name' => $datos['first_name'],
                    'last_name' => $datos['last_name'],
                    'phone' => $datos['phone'],
                    'password_hash' => Hash::make('password'),
                    'role_id' => $rol->id,
                    'user_type_id' => $tipo->id,
                    'status' => 1,
                    'email_verified_at' => now(),
                ]
            );

            UserPermission::firstOrCreate(
                ['patient_id' => $medico->id],
                UserPermission::defaultsFor(UserTypeName::Doctor->value)
            );

            $medico->specialties()->syncWithoutDetaching(
                Specialty::whereIn('name', $datos['especialidades'])->pluck('id')->all()
            );

            foreach ($datos['franjas'] as [$dias, $inicio, $fin]) {
                foreach ($dias as $dia) {
                    DoctorSchedule::firstOrCreate(
                        ['doctor_id' => $medico->id, 'day_of_week' => $dia, 'start_time' => $inicio],
                        ['end_time' => $fin]
                    );
                }
            }
        }
    }

    private function sembrarPacientes(): void
    {
        $pacientes = [
            ['sofia.ramirez@hospital.test', 'Sofía', 'Ramírez', '11 5231-4410', 'OSDE', '310458796'],
            ['diego.sosa@hospital.test', 'Diego', 'Sosa', '11 6102-8837', 'Swiss Medical', 'SM-7740213'],
            ['camila.torres@hospital.test', 'Camila', 'Torres', '11 4455-9021', 'Galeno', 'GAL-2201964'],
            ['julian.medina@hospital.test', 'Julián', 'Medina', '11 5987-3302', 'Sancor Salud', 'SS-5518830'],
            ['valentina.romero@hospital.test', 'Valentina', 'Romero', '11 6644-1275', null, null],
            ['facundo.alvarez@hospital.test', 'Facundo', 'Álvarez', '11 4120-7743', null, null],
            ['agustina.castro@hospital.test', 'Agustina', 'Castro', '11 5073-6618', null, null],
            ['tomas.herrera@hospital.test', 'Tomás', 'Herrera', '11 6209-5584', null, null],
        ];

        $rol = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $tipo = UserType::where('name', UserTypeName::Patient->value)->firstOrFail();

        foreach ($pacientes as [$email, $nombre, $apellido, $telefono, $obraSocial, $afiliado]) {
            $paciente = Patient::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $nombre,
                    'last_name' => $apellido,
                    'phone' => $telefono,
                    'password_hash' => Hash::make('password'),
                    'role_id' => $rol->id,
                    'user_type_id' => $tipo->id,
                    'status' => 1,
                    'email_verified_at' => now(),
                ]
            );

            UserPermission::firstOrCreate(
                ['patient_id' => $paciente->id],
                UserPermission::defaultsFor(UserTypeName::Patient->value)
            );

            if ($obraSocial) {
                PatientHealthInsurance::firstOrCreate(
                    ['patient_id' => $paciente->id],
                    [
                        'health_insurance_id' => HealthInsurance::where('name', $obraSocial)->firstOrFail()->id,
                        'member_number' => $afiliado,
                    ]
                );
            }
        }
    }

    private function sembrarPersonalAdministrativo(): void
    {
        $rolAdministrador = Role::where('name', RoleName::Administrator->value)->firstOrFail();
        $rolOperativo = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $tipoSecretaria = UserType::where('name', UserTypeName::Secretary->value)->firstOrFail();

        Patient::firstOrCreate(
            ['email' => 'gabriela.dominguez@hospital.test'],
            [
                'first_name' => 'Gabriela',
                'last_name' => 'Domínguez',
                'phone' => '11 5340-9926',
                'password_hash' => Hash::make('password'),
                'role_id' => $rolAdministrador->id,
                'status' => 1,
                'is_owner' => false,
                'email_verified_at' => now(),
            ]
        );

        $exAdministradora = Patient::firstOrCreate(
            ['email' => 'marcela.ibarra@hospital.test'],
            [
                'first_name' => 'Marcela',
                'last_name' => 'Ibarra',
                'phone' => '11 4687-2150',
                'password_hash' => Hash::make('password'),
                'role_id' => $rolOperativo->id,
                'user_type_id' => $tipoSecretaria->id,
                'status' => 1,
                'is_owner' => false,
                'email_verified_at' => now(),
            ]
        );

        UserPermission::firstOrCreate(
            ['patient_id' => $exAdministradora->id],
            UserPermission::defaultsFor(UserTypeName::Secretary->value)
        );
    }
}
