<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const CUENTAS = [
        ['Super Administrador', 'superadmin@hospital.test'],
        ['Administrador propietario', 'admin@hospital.test'],
        ['Administrador', 'gabriela.dominguez@hospital.test'],
        ['Médico (cuenta principal)', 'medico@hospital.test'],
        ['Médico', 'carolina.benitez@hospital.test'],
        ['Médico', 'lucia.marchetti@hospital.test'],
        ['Médico', 'martin.acosta@hospital.test'],
        ['Secretaria', 'secretaria@hospital.test'],
        ['Secretaria (ex administradora)', 'marcela.ibarra@hospital.test'],
        ['Paciente (cuenta principal)', 'paciente@hospital.test'],
        ['Paciente', 'sofia.ramirez@hospital.test'],
        ['Paciente', 'diego.sosa@hospital.test'],
        ['Paciente', 'camila.torres@hospital.test'],
        ['Paciente', 'julian.medina@hospital.test'],
        ['Paciente', 'valentina.romero@hospital.test'],
        ['Paciente', 'facundo.alvarez@hospital.test'],
        ['Paciente', 'agustina.castro@hospital.test'],
        ['Paciente', 'tomas.herrera@hospital.test'],
    ];

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserTypeSeeder::class,
            AdminActionSeeder::class,
            SuperAdminSeeder::class,
            AdministratorSeeder::class,
            DoctorSeeder::class,
            PatientSeeder::class,
            SecretarySeeder::class,
            SpecialtySeeder::class,
            DoctorSpecialtySeeder::class,
            SalaSeeder::class,
            HealthInsuranceSeeder::class,
            DoctorScheduleSeeder::class,
            PaymentMethodSeeder::class,
            PaymentStatusSeeder::class,
            DemoUsuariosSeeder::class,
            DemoTurnosSeeder::class,
            DemoPagosSeeder::class,
            DemoNotificacionesSeeder::class,
            DemoDocumentosSeeder::class,
            DemoAuditoriaSeeder::class,
        ]);

        $this->mostrarCuentas();
    }

    private function mostrarCuentas(): void
    {
        $nombres = Patient::whereIn('email', array_column(self::CUENTAS, 1))
            ->get()
            ->mapWithKeys(fn (Patient $cuenta) => [$cuenta->email => "{$cuenta->first_name} {$cuenta->last_name}"]);

        $filas = array_map(
            fn (array $cuenta) => [$cuenta[0], $nombres[$cuenta[1]] ?? '-', $cuenta[1], 'password'],
            self::CUENTAS
        );

        $this->command?->table(['Rol', 'Nombre', 'Email', 'Contraseña'], $filas);
    }
}
