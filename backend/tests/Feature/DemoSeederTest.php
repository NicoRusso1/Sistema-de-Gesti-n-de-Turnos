<?php

namespace Tests\Feature;

use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\PatientHealthInsurance;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoTurnosSeeder;
use Database\Seeders\DemoUsuariosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
    }

    private function sembrar(): void
    {
        $this->seed([DatabaseSeeder::class, DemoUsuariosSeeder::class, DemoTurnosSeeder::class]);
    }

    public function test_siembra_las_personas_de_la_demo_sin_romper_las_cuentas_existentes(): void
    {
        $this->sembrar();

        foreach (['superadmin', 'admin', 'medico', 'paciente', 'secretaria'] as $cuenta) {
            $this->assertDatabaseHas('patients', ['email' => "{$cuenta}@hospital.test"]);
        }

        $this->assertSame(3, Patient::doctors()->where('email', '!=', 'medico@hospital.test')->count());
        $this->assertSame(0, PatientHealthInsurance::where('patient_id', Patient::where('email', 'paciente@hospital.test')->value('id'))->count());
        $this->assertSame(4, PatientHealthInsurance::distinct('health_insurance_id')->count('health_insurance_id'));
        $this->assertSame('Administrator', Patient::where('email', 'gabriela.dominguez@hospital.test')->first()->role->name);
        $this->assertSame('OperationalUser', Patient::where('email', 'marcela.ibarra@hospital.test')->first()->role->name);
    }

    public function test_las_franjas_de_los_medicos_nuevos_son_de_lunes_a_viernes(): void
    {
        $this->sembrar();

        $medico = Patient::where('email', 'carolina.benitez@hospital.test')->first();

        $this->assertSame([1, 2, 3, 4, 5], DoctorSchedule::where('doctor_id', $medico->id)->orderBy('day_of_week')->pluck('day_of_week')->all());
        $this->assertSame(['Pediatria'], $medico->specialties()->pluck('name')->all());
    }

    public function test_correrlo_dos_veces_no_duplica_personas_ni_horarios(): void
    {
        $this->sembrar();
        $cantidades = [Patient::count(), DoctorSchedule::count(), PatientHealthInsurance::count()];

        $this->sembrar();

        $this->assertSame($cantidades, [Patient::count(), DoctorSchedule::count(), PatientHealthInsurance::count()]);
    }
}
