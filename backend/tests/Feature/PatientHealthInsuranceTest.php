<?php

namespace Tests\Feature;

use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\HealthInsurance;
use App\Models\Patient;
use App\Models\PatientHealthInsurance;
use App\Models\UserPermission;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PatientHealthInsuranceTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private HealthInsurance $osde;
    private HealthInsurance $swiss;
    private HealthInsurance $inactive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patient = $this->createPatient(UserTypeName::Patient);

        $this->osde = HealthInsurance::create(['name' => 'OSDE', 'discount_percentage' => 30, 'status' => 1]);
        $this->swiss = HealthInsurance::create(['name' => 'Swiss Medical', 'discount_percentage' => 25, 'status' => 1]);
        $this->inactive = HealthInsurance::create(['name' => 'Galeno', 'discount_percentage' => 20, 'status' => 0]);
    }

    private function createPatient(UserTypeName $type): Patient
    {
        return Patient::factory()->create([
            'user_type_id' => UserType::where('name', $type->value)->first()->id,
        ]);
    }

    public function test_sin_token_no_puede_ver_su_obra_social(): void
    {
        $this->getJson('/api/me/health-insurance')->assertStatus(401);
    }

    public function test_paciente_sin_obra_social_recibe_null(): void
    {
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/me/health-insurance')
            ->assertOk()
            ->assertJson(['affiliation' => null]);
    }

    public function test_paciente_puede_cargar_su_obra_social(): void
    {
        Sanctum::actingAs($this->patient);

        $this->putJson('/api/me/health-insurance', [
            'health_insurance_id' => $this->osde->id,
            'member_number' => 'OSDE-12345678',
        ])
            ->assertStatus(201)
            ->assertJsonPath('affiliation.health_insurance.name', 'OSDE')
            ->assertJsonPath('affiliation.member_number', 'OSDE-12345678');

        $this->assertDatabaseHas('patient_health_insurance', [
            'patient_id' => $this->patient->id,
            'health_insurance_id' => $this->osde->id,
            'member_number' => 'OSDE-12345678',
        ]);
    }

    public function test_paciente_puede_ver_su_obra_social_cargada(): void
    {
        PatientHealthInsurance::create([
            'patient_id' => $this->patient->id,
            'health_insurance_id' => $this->osde->id,
            'member_number' => 'OSDE-1',
        ]);

        Sanctum::actingAs($this->patient);

        $this->getJson('/api/me/health-insurance')
            ->assertOk()
            ->assertJsonPath('affiliation.member_number', 'OSDE-1')
            ->assertJsonPath('affiliation.health_insurance.discount_percentage', '30.00');
    }

    public function test_paciente_puede_modificar_su_obra_social(): void
    {
        PatientHealthInsurance::create([
            'patient_id' => $this->patient->id,
            'health_insurance_id' => $this->osde->id,
            'member_number' => 'OSDE-1',
        ]);

        Sanctum::actingAs($this->patient);

        $this->putJson('/api/me/health-insurance', [
            'health_insurance_id' => $this->swiss->id,
            'member_number' => 'SWISS-2',
        ])
            ->assertOk()
            ->assertJsonPath('affiliation.health_insurance.name', 'Swiss Medical');

        $this->assertDatabaseCount('patient_health_insurance', 1);
        $this->assertDatabaseHas('patient_health_insurance', [
            'patient_id' => $this->patient->id,
            'health_insurance_id' => $this->swiss->id,
            'member_number' => 'SWISS-2',
        ]);
    }

    public function test_paciente_puede_eliminar_su_obra_social(): void
    {
        PatientHealthInsurance::create([
            'patient_id' => $this->patient->id,
            'health_insurance_id' => $this->osde->id,
            'member_number' => 'OSDE-1',
        ]);

        Sanctum::actingAs($this->patient);

        $this->deleteJson('/api/me/health-insurance')->assertOk();

        $this->assertDatabaseMissing('patient_health_insurance', ['patient_id' => $this->patient->id]);
    }

    public function test_eliminar_sin_obra_social_cargada_devuelve_404(): void
    {
        Sanctum::actingAs($this->patient);

        $this->deleteJson('/api/me/health-insurance')->assertStatus(404);
    }

    public function test_no_se_puede_cargar_una_obra_social_inactiva(): void
    {
        Sanctum::actingAs($this->patient);

        $this->putJson('/api/me/health-insurance', [
            'health_insurance_id' => $this->inactive->id,
            'member_number' => 'GAL-1',
        ])->assertStatus(422)->assertJsonValidationErrors('health_insurance_id');

        $this->assertDatabaseCount('patient_health_insurance', 0);
    }

    public function test_no_se_puede_cargar_una_obra_social_inexistente(): void
    {
        Sanctum::actingAs($this->patient);

        $this->putJson('/api/me/health-insurance', [
            'health_insurance_id' => 999,
            'member_number' => 'X-1',
        ])->assertStatus(422)->assertJsonValidationErrors('health_insurance_id');
    }

    public function test_el_numero_de_afiliado_es_obligatorio(): void
    {
        Sanctum::actingAs($this->patient);

        $this->putJson('/api/me/health-insurance', [
            'health_insurance_id' => $this->osde->id,
        ])->assertStatus(422)->assertJsonValidationErrors('member_number');
    }

    public function test_un_paciente_no_ve_la_obra_social_de_otro_paciente(): void
    {
        $other = $this->createPatient(UserTypeName::Patient);

        PatientHealthInsurance::create([
            'patient_id' => $other->id,
            'health_insurance_id' => $this->osde->id,
            'member_number' => 'OTRO-1',
        ]);

        Sanctum::actingAs($this->patient);

        $this->getJson('/api/me/health-insurance')->assertOk()->assertJson(['affiliation' => null]);
    }

    public function test_el_catalogo_no_muestra_obras_sociales_inactivas_al_paciente(): void
    {
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/health-insurances')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonMissing(['name' => 'Galeno']);
    }

    public function test_el_paciente_no_puede_ver_una_obra_social_inactiva(): void
    {
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/health-insurances/' . $this->inactive->id)->assertStatus(404);
    }

    public function test_el_paciente_no_puede_crear_obras_sociales(): void
    {
        Sanctum::actingAs($this->patient);

        $this->postJson('/api/health-insurances', [
            'name' => 'Nueva',
            'discount_percentage' => 10,
        ])->assertStatus(403);
    }

    public function test_el_medico_ve_la_obra_social_de_sus_pacientes(): void
    {
        $doctor = $this->createPatient(UserTypeName::Doctor);

        UserPermission::create([
            'patient_id' => $doctor->id,
            'view_assigned_patients' => true,
        ]);

        Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'reservado',
        ]);

        PatientHealthInsurance::create([
            'patient_id' => $this->patient->id,
            'health_insurance_id' => $this->osde->id,
            'member_number' => 'OSDE-1',
        ]);

        Sanctum::actingAs($doctor);

        $this->getJson('/api/medicos/me/pacientes')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.health_insurance.name', 'OSDE')
            ->assertJsonPath('0.health_insurance_number', 'OSDE-1');
    }
}
