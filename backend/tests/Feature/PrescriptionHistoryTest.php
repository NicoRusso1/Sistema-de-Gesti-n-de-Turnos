<?php

namespace Tests\Feature;

use App\Enums\DocumentIssueStatus;
use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserType;
use App\Support\Documents;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GT-220: historial de recetas del paciente.
 */
class PrescriptionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private Patient $otherPatient;
    private Patient $doctor;
    private Patient $otherDoctor;
    private Patient $admin;
    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient, ['first_name' => 'Lucia', 'last_name' => 'Gomez']);
        $this->otherPatient = $this->createUser(UserTypeName::Patient);
        $this->doctor = $this->createUser(UserTypeName::Doctor, ['first_name' => 'Ana', 'last_name' => 'Perez']);
        $this->otherDoctor = $this->createUser(UserTypeName::Doctor);
        $this->admin = $this->createUser(null, [], RoleName::Administrator);
        $this->specialty = Specialty::create(['name' => 'Clínica Médica']);
    }

    private function createUser(?UserTypeName $type, array $attributes = [], RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create($attributes + [
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    private function prescription(
        Patient $patient,
        Patient $doctor,
        $issueDate = null,
        DocumentIssueStatus $estado = DocumentIssueStatus::Issued,
    ): Prescription {
        $appointment = Appointment::create([
            'patient_id'   => $patient->id,
            'doctor_id'    => $doctor->id,
            'specialty_id' => $this->specialty->id,
            'scheduled_at' => now()->subDay(),
            'status'       => Appointment::STATUS_COMPLETED,
        ]);

        $issued = $estado === DocumentIssueStatus::Issued;

        return Prescription::create([
            'appointment_id' => $appointment->id,
            'doctor_id'      => $doctor->id,
            'patient_id'     => $patient->id,
            'content'        => 'Ibuprofeno 400 mg',
            'issue_date'     => $issued ? ($issueDate ?? now()) : null,
            'file_url'       => $issued ? "recetas/receta_{$appointment->id}.pdf" : null,
            'estado_emision' => $estado,
        ]);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/recetas')->assertStatus(401);
    }

    public function test_paciente_ve_sus_recetas_con_medico_especialidad_fecha_y_turno(): void
    {
        $receta = $this->prescription($this->patient, $this->doctor);

        Sanctum::actingAs($this->patient);

        $this->getJson('/api/recetas')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $receta->id)
            ->assertJsonPath('data.0.turno_id', $receta->appointment_id)
            ->assertJsonPath('data.0.medico', 'Ana Perez')
            ->assertJsonPath('data.0.especialidad', 'Clínica Médica')
            ->assertJsonPath('data.0.tiene_archivo', true)
            ->assertJsonPath('data.0.fecha_emision', $receta->issue_date->toIso8601String());
    }

    public function test_recetas_ordenadas_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $vieja = $this->prescription($this->patient, $this->doctor, now()->subDays(10));
        $nueva = $this->prescription($this->patient, $this->doctor, now());
        $media = $this->prescription($this->patient, $this->otherDoctor, now()->subDays(3));

        Sanctum::actingAs($this->patient);

        $this->getJson('/api/recetas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $nueva->id)
            ->assertJsonPath('data.1.id', $media->id)
            ->assertJsonPath('data.2.id', $vieja->id);
    }

    public function test_paciente_nunca_ve_recetas_de_otros_pacientes(): void
    {
        $propia = $this->prescription($this->patient, $this->doctor);
        $this->prescription($this->otherPatient, $this->doctor);

        Sanctum::actingAs($this->patient);

        $this->getJson('/api/recetas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propia->id);
    }

    public function test_paciente_no_ve_recetas_fallidas_o_pendientes(): void
    {
        $this->prescription($this->patient, $this->doctor, null, DocumentIssueStatus::Failed);
        $this->prescription($this->patient, $this->doctor, null, DocumentIssueStatus::Pending);

        Sanctum::actingAs($this->patient);

        $this->getJson('/api/recetas')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_paciente_sin_recetas_recibe_listado_vacio(): void
    {
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/recetas')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('total', 0);
    }

    public function test_medico_ve_solo_las_recetas_que_emitio_incluidas_las_fallidas(): void
    {
        $emitida = $this->prescription($this->patient, $this->doctor);
        $fallida = $this->prescription($this->otherPatient, $this->doctor, null, DocumentIssueStatus::Failed);
        $this->prescription($this->patient, $this->otherDoctor);

        Sanctum::actingAs($this->doctor);

        $response = $this->getJson('/api/recetas')->assertOk()->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing([$emitida->id, $fallida->id], array_column($response->json('data'), 'id'));
        // Las fallidas no tienen fecha de emisión: quedan al final
        $response->assertJsonPath('data.1.id', $fallida->id)
            ->assertJsonPath('data.1.estado_emision', 'fallida')
            ->assertJsonPath('data.1.tiene_archivo', false)
            ->assertJsonPath('data.0.paciente', 'Lucia Gomez');
    }

    public function test_paciente_puede_ver_y_descargar_su_receta(): void
    {
        $receta = $this->prescription($this->patient, $this->doctor);
        Documents::disk()->put($receta->file_url, '%PDF-1.4 receta');

        Sanctum::actingAs($this->patient);

        $this->get("/api/prescriptions/{$receta->id}/view")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get("/api/prescriptions/{$receta->id}/download")
            ->assertOk()
            ->assertDownload("Receta_{$receta->id}_Turno_{$receta->appointment_id}.pdf");
    }

    public function test_paciente_no_puede_descargar_receta_ajena(): void
    {
        $ajena = $this->prescription($this->otherPatient, $this->doctor);
        Documents::disk()->put($ajena->file_url, '%PDF-1.4 receta');

        Sanctum::actingAs($this->patient);

        $this->getJson("/api/prescriptions/{$ajena->id}/download")->assertStatus(403);
        $this->getJson("/api/prescriptions/{$ajena->id}/view")->assertStatus(403);
    }

    public function test_administrador_ve_todas_las_recetas_emitidas(): void
    {
        $this->prescription($this->patient, $this->doctor);
        $this->prescription($this->otherPatient, $this->otherDoctor);
        $this->prescription($this->otherPatient, $this->doctor, null, DocumentIssueStatus::Failed);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/recetas')->assertOk()->assertJsonPath('total', 2);
    }
}
