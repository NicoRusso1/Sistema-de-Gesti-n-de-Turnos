<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Patient;
use App\Models\UserType;
use App\Support\Documents;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificadosConsultaTest extends TestCase
{
    use RefreshDatabase;

    private Patient $pacienteA;
    private Patient $pacienteB;
    private Patient $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $patientType = UserType::where('name', UserTypeName::Patient->value)->first();
        $doctorType = UserType::where('name', UserTypeName::Doctor->value)->first();

        $this->pacienteA = Patient::factory()->create(['user_type_id' => $patientType->id]);
        $this->pacienteB = Patient::factory()->create(['user_type_id' => $patientType->id]);
        $this->medico = Patient::factory()->create(['user_type_id' => $doctorType->id]);
    }

    private function crearCertificadoPara(Patient $paciente, string $statusTurno = 'abonado', $issueDate = null): Certificate
    {
        $appointment = Appointment::create([
            'patient_id'   => $paciente->id,
            'doctor_id'    => $this->medico->id,
            'scheduled_at' => now(),
            'status'       => $statusTurno,
        ]);

        return Certificate::create([
            'appointment_id'  => $appointment->id,
            'certificate_url' => "certificados/test_{$appointment->id}.pdf",
            'issue_date'      => $issueDate ?? now(),
        ]);
    }

    /**
     * Test 1: El paciente accede ÚNICAMENTE a los certificados de sus turnos (Criterio 1).
     */
    public function test_paciente_solo_ve_sus_propios_certificados(): void
    {
        $certA = $this->crearCertificadoPara($this->pacienteA);
        $certB = $this->crearCertificadoPara($this->pacienteB);

        Sanctum::actingAs($this->pacienteA);

        $response = $this->getJson('/api/certificados');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $certA->id);
    }

    /**
     * Test 2: Se listan ordenados por fecha de emisión descendente (Criterio 2).
     */
    public function test_certificados_se_ordenan_por_fecha_emision_descendente(): void
    {
        $antiguo = $this->crearCertificadoPara($this->pacienteA, 'abonado', now()->subDays(5));
        $reciente = $this->crearCertificadoPara($this->pacienteA, 'abonado', now());

        Sanctum::actingAs($this->pacienteA);

        $response = $this->getJson('/api/certificados');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $reciente->id)
            ->assertJsonPath('data.1.id', $antiguo->id);
    }

    /**
     * Test 3: Certificados de turnos cancelados se marcan como no válidos (Criterio 4).
     */
    public function test_certificado_de_turno_cancelado_se_marca_como_no_valido(): void
    {
        $certCancelado = $this->crearCertificadoPara($this->pacienteA, 'cancelado');

        Sanctum::actingAs($this->pacienteA);

        $response = $this->getJson('/api/certificados');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.valido', false)
            ->assertJsonPath('data.0.estado_turno', 'cancelado')
            ->assertJsonPath('data.0.motivo_invalidez', 'El turno fue cancelado con posterioridad a la emisión');
    }

    /**
     * Test 4: La descarga requiere sesión activa (Criterio 3).
     */
    public function test_descarga_sin_sesion_activa_es_rechazada(): void
    {
        $cert = $this->crearCertificadoPara($this->pacienteA);

        $this->getJson("/api/certificados/{$cert->id}/download")
            ->assertStatus(401);
    }

    /**
     * Test 5: Validación de propiedad en descarga (Un paciente no puede descargar certificado ajeno) (Criterio 3).
     */
    public function test_paciente_no_puede_descargar_certificado_ajeno(): void
    {
        $certA = $this->crearCertificadoPara($this->pacienteA);

        // Paciente B intenta descargar certificado de Paciente A
        Sanctum::actingAs($this->pacienteB);

        $this->getJson("/api/certificados/{$certA->id}/download")
            ->assertStatus(403)
            ->assertJson(['message' => 'No tenés permiso para descargar este certificado.']);
    }

    /**
     * Test 6: Paciente descarga exitosamente su propio certificado si el archivo existe.
     */
    public function test_paciente_puede_descargar_su_propio_certificado(): void
    {
        $cert = $this->crearCertificadoPara($this->pacienteA);

        // Crear archivo temporal en el disco privado simulado
        Documents::disk()->put($cert->certificate_url, '%PDF-1.4 Mock PDF Content');

        Sanctum::actingAs($this->pacienteA);

        $response = $this->get("/api/certificados/{$cert->id}/download");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }
}