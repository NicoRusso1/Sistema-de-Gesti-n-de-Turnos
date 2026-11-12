<?php

namespace Tests\Feature;

use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\HealthInsurance;
use App\Models\Pago;
use App\Models\Patient;
use App\Models\PatientHealthInsurance;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PagosTurnoTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private Patient $doctor;
    private HealthInsurance $osde;
    private HealthInsurance $inactiva;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $patientType = UserType::where('name', UserTypeName::Patient->value)->first();
        $doctorType = UserType::where('name', UserTypeName::Doctor->value)->first();

        $this->patient = Patient::factory()->create(['user_type_id' => $patientType->id]);
        $this->doctor = Patient::factory()->create(['user_type_id' => $doctorType->id]);

        // Obra social activa con 30% de descuento
        $this->osde = HealthInsurance::create([
            'name' => 'OSDE',
            'discount_percentage' => 30.00,
            'status' => 1,
        ]);

        // Obra social inactiva/no vigente con 20%
        $this->inactiva = HealthInsurance::create([
            'name' => 'Inactiva Med',
            'discount_percentage' => 20.00,
            'status' => 0,
        ]);
    }

    private function crearTurno(float $montoBase = 10000.00): Appointment
    {
        return Appointment::create([
            'patient_id'   => $this->patient->id,
            'doctor_id'    => $this->doctor->id,
            'scheduled_at' => now()->addDays(2),
            'status'       => 'reservado',
            'monto_base'   => $montoBase,
        ]);
    }

    /**
     * Test 1: Pago aprobado con obra social y aplicación automática del descuento del 30%.
     */
    public function test_pago_aprobado_con_obra_social_aplica_descuento_y_marca_abonado(): void
    {
        Sanctum::actingAs($this->patient);

        // Asociar OSDE al paciente
        PatientHealthInsurance::create([
            'patient_id'          => $this->patient->id,
            'health_insurance_id' => $this->osde->id,
            'member_number'       => 'OSDE-998877',
        ]);

        $turno = $this->crearTurno(10000.00);

        $response = $this->postJson('/api/pagos', [
            'turno_id'    => $turno->id,
            'token_pago'  => 'tok_approved_valid',
            'metodo_pago' => 'tarjeta_credito',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('estado', 'aprobado')
            ->assertJsonPath('trazabilidad.monto_original', 10000)
            ->assertJsonPath('trazabilidad.descuento_aplicado', 3000)
            ->assertJsonPath('trazabilidad.monto_final', 7000);

        // Verificar persistencia en tabla pagos
        $this->assertDatabaseHas('pagos', [
            'turno_id'           => $turno->id,
            'monto_original'     => 10000.00,
            'descuento_aplicado' => 3000.00,
            'monto_final'        => 7000.00,
            'estado'             => 'aprobado',
        ]);

        // Verificar que el turno cambió a 'abonado'
        $this->assertDatabaseHas('appointments', [
            'id'     => $turno->id,
            'status' => 'abonado',
        ]);
    }

    /**
     * Test 2: Pago rechazado por la pasarela de pagos.
     */
    public function test_pago_rechazado_registra_estado_y_turno_sigue_reservado(): void
    {
        Sanctum::actingAs($this->patient);

        $turno = $this->crearTurno(8000.00);

        $response = $this->postJson('/api/pagos', [
            'turno_id'    => $turno->id,
            'token_pago'  => 'tok_rejected',
            'metodo_pago' => 'tarjeta_debito',
        ]);

        $response->assertStatus(402)
            ->assertJsonPath('estado', 'rechazado');

        // Registro del pago en estado rechazado con trazabilidad
        $this->assertDatabaseHas('pagos', [
            'turno_id'       => $turno->id,
            'monto_original' => 8000.00,
            'monto_final'    => 8000.00,
            'estado'         => 'rechazado',
        ]);

        // El turno no debe pasar a abonado
        $this->assertDatabaseHas('appointments', [
            'id'     => $turno->id,
            'status' => 'reservado',
        ]);
    }

    /**
     * Test 3: Paciente sin obra social debe pagar el monto original completo.
     */
    public function test_pago_sin_obra_social_cobra_el_monto_completo(): void
    {
        Sanctum::actingAs($this->patient);

        $turno = $this->crearTurno(12000.00);

        $response = $this->postJson('/api/pagos', [
            'turno_id'    => $turno->id,
            'token_pago'  => 'tok_valid_test',
            'metodo_pago' => 'tarjeta_credito',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('trazabilidad.monto_original', 12000)
            ->assertJsonPath('trazabilidad.descuento_aplicado', 0)
            ->assertJsonPath('trazabilidad.monto_final', 12000)
            ->assertJsonPath('trazabilidad.tiene_obra_social', false);

        $this->assertDatabaseHas('pagos', [
            'turno_id'           => $turno->id,
            'descuento_aplicado' => 0.00,
            'monto_final'        => 12000.00,
            'estado'             => 'aprobado',
        ]);
    }

    /**
     * Test 4: Paciente con obra social no vigente (status = 0) no recibe descuento.
     */
    public function test_obra_social_inactiva_no_aplica_descuento(): void
    {
        Sanctum::actingAs($this->patient);

        PatientHealthInsurance::create([
            'patient_id'          => $this->patient->id,
            'health_insurance_id' => $this->inactiva->id,
            'member_number'       => 'INAC-1234',
        ]);

        $turno = $this->crearTurno(15000.00);

        $response = $this->postJson('/api/pagos', [
            'turno_id'   => $turno->id,
            'token_pago' => 'tok_valid_test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('trazabilidad.descuento_aplicado', 0)
            ->assertJsonPath('trazabilidad.monto_final', 15000)
            ->assertJsonPath('trazabilidad.tiene_obra_social', false);
    }
}