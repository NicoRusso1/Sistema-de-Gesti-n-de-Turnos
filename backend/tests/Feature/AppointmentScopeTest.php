<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserPermission;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patientA;
    private Patient $patientB;
    private Patient $doctorA;
    private Patient $doctorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patientA = $this->createUser(UserTypeName::Patient);
        $this->patientB = $this->createUser(UserTypeName::Patient);
        $this->doctorA = $this->createUser(UserTypeName::Doctor);
        $this->doctorB = $this->createUser(UserTypeName::Doctor);

        // A con A, B con B, y A con el médico B
        $this->createAppointment($this->patientA, $this->doctorA);
        $this->createAppointment($this->patientB, $this->doctorB);
        $this->createAppointment($this->patientA, $this->doctorB);
    }

    private function createUser(?UserTypeName $type, RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create([
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    private function createAppointment(Patient $patient, Patient $doctor): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDay(),
            'status' => 'reservado',
        ]);
    }

    private function setPermissions(Patient $user, array $flags): void
    {
        UserPermission::updateOrCreate(
            ['patient_id' => $user->id],
            array_merge(UserPermission::defaultsFor($user->userType?->name), $flags)
        );
    }

    public function test_sin_token_no_puede_ver_la_agenda(): void
    {
        $this->getJson('/api/appointments')->assertStatus(401);
    }

    public function test_paciente_solo_ve_sus_turnos(): void
    {
        Sanctum::actingAs($this->patientA);

        $response = $this->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonPath('scope', Appointment::SCOPE_OWN)
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $appointment) {
            $this->assertSame($this->patientA->id, $appointment['patient_id']);
        }
    }

    public function test_paciente_no_puede_ver_turnos_ajenos_filtrando_por_patient_id(): void
    {
        Sanctum::actingAs($this->patientA);

        $this->getJson('/api/appointments?patient_id=' . $this->patientB->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.patient_id', $this->patientA->id);
    }

    public function test_paciente_sin_permiso_ver_turnos_propios_recibe_403(): void
    {
        $this->setPermissions($this->patientA, ['view_own_appointments' => false]);
        Sanctum::actingAs($this->patientA);

        $this->getJson('/api/appointments')->assertStatus(403);
    }

    public function test_medico_solo_ve_turnos_donde_esta_asignado(): void
    {
        Sanctum::actingAs($this->doctorB);

        $response = $this->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonPath('scope', Appointment::SCOPE_ASSIGNED)
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $appointment) {
            $this->assertSame($this->doctorB->id, $appointment['doctor_id']);
        }
    }

    public function test_medico_sin_permiso_ver_pacientes_asignados_recibe_403(): void
    {
        $this->setPermissions($this->doctorA, ['view_assigned_patients' => false]);
        Sanctum::actingAs($this->doctorA);

        $this->getJson('/api/appointments')->assertStatus(403);
    }

    public function test_secretaria_ve_todos_los_turnos(): void
    {
        $secretary = $this->createUser(UserTypeName::Secretary);
        Sanctum::actingAs($secretary);

        $this->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonPath('scope', Appointment::SCOPE_ALL)
            ->assertJsonCount(3, 'data');
    }

    public function test_secretaria_puede_filtrar_por_paciente(): void
    {
        $secretary = $this->createUser(UserTypeName::Secretary);
        Sanctum::actingAs($secretary);

        $this->getJson('/api/appointments?patient_id=' . $this->patientB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_secretaria_sin_permiso_ver_todos_los_turnos_recibe_403(): void
    {
        $secretary = $this->createUser(UserTypeName::Secretary);
        $this->setPermissions($secretary, ['view_all_appointments' => false]);
        Sanctum::actingAs($secretary);

        $this->getJson('/api/appointments')->assertStatus(403);
    }

    public function test_administrador_ve_todos_los_turnos(): void
    {
        $admin = $this->createUser(null, RoleName::Administrator);
        Sanctum::actingAs($admin);

        $this->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonPath('scope', Appointment::SCOPE_ALL)
            ->assertJsonCount(3, 'data');
    }
}
