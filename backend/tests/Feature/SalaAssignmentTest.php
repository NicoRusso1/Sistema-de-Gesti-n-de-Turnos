<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Sala;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private Patient $doctor;
    private Patient $secretary;
    private Specialty $cardio;
    private Specialty $derma;
    private string $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient);
        $this->doctor = $this->createUser(UserTypeName::Doctor);
        $this->secretary = $this->createUser(UserTypeName::Secretary);

        $this->cardio = Specialty::create(['name' => 'Cardiología']);
        $this->derma = Specialty::create(['name' => 'Dermatología']);

        $this->slot = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function createUser(?UserTypeName $type, RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create([
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    private function createSala(string $numero, ?Specialty $specialty, string $estado = 'disponible'): Sala
    {
        return Sala::create([
            'numero' => $numero,
            'piso' => 1,
            'especialidad_id' => $specialty?->id,
            'estado' => $estado,
        ]);
    }

    private function book(?Patient $doctor = null, ?string $slot = null): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->patient);

        return $this->postJson('/api/appointments', [
            'doctor_id' => ($doctor ?? $this->doctor)->id,
            'specialty_id' => $this->cardio->id,
            'scheduled_at' => $slot ?? $this->slot,
            'monto_base' => 10000,
        ]);
    }

    private function createAppointment(?Sala $sala, ?string $slot = null, string $status = 'reservado'): Appointment
    {
        return Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->createUser(UserTypeName::Doctor)->id,
            'specialty_id' => $this->cardio->id,
            'sala_id' => $sala?->id,
            'scheduled_at' => $slot ?? $this->slot,
            'status' => $status,
        ]);
    }

    // --- GT-41: asignación automática ---

    public function test_reserva_asigna_sala_disponible_de_la_especialidad(): void
    {
        $this->createSala('D1', $this->derma);
        $this->createSala('C0', $this->cardio, 'fuera_de_servicio');
        $sala = $this->createSala('C1', $this->cardio);

        $this->book()
            ->assertStatus(201)
            ->assertJsonPath('appointment.sala_id', $sala->id);
    }

    public function test_reserva_salta_la_sala_ocupada_en_ese_horario(): void
    {
        $ocupada = $this->createSala('C1', $this->cardio);
        $libre = $this->createSala('C2', $this->cardio);
        $this->createAppointment($ocupada);

        $this->book()
            ->assertStatus(201)
            ->assertJsonPath('appointment.sala_id', $libre->id);
    }

    public function test_un_turno_cancelado_no_ocupa_la_sala(): void
    {
        $sala = $this->createSala('C1', $this->cardio);
        $this->createAppointment($sala, null, 'cancelado');

        $this->book()
            ->assertStatus(201)
            ->assertJsonPath('appointment.sala_id', $sala->id);
    }

    public function test_sin_sala_libre_el_turno_se_crea_sin_sala(): void
    {
        $sala = $this->createSala('C1', $this->cardio);
        $this->createAppointment($sala);

        $this->book()
            ->assertStatus(201)
            ->assertJsonPath('appointment.sala_id', null);
    }

    // --- GT-42: cruce de horarios ---

    public function test_turnos_en_bloques_distintos_comparten_sala(): void
    {
        $sala = $this->createSala('C1', $this->cardio);
        $this->createAppointment($sala);

        $siguiente = now()->addDay()->setTime(10, Appointment::DURATION_MINUTES)->format('Y-m-d H:i');

        $this->book(null, $siguiente)
            ->assertStatus(201)
            ->assertJsonPath('appointment.sala_id', $sala->id);
    }

    public function test_no_se_puede_asignar_a_mano_una_sala_con_cruce(): void
    {
        $sala = $this->createSala('C1', $this->cardio);
        $this->createAppointment($sala);
        $turno = $this->createAppointment(null);

        Sanctum::actingAs($this->secretary);

        $this->patchJson("/api/appointments/{$turno->id}/sala", ['sala_id' => $sala->id])
            ->assertStatus(409);

        $this->assertNull($turno->fresh()->sala_id);
    }

    public function test_reprogramar_a_un_horario_ocupado_cambia_de_sala(): void
    {
        $salaA = $this->createSala('C1', $this->cardio);
        $salaB = $this->createSala('C2', $this->cardio);
        $otroHorario = now()->addDay()->setTime(11, 0)->format('Y-m-d H:i');

        $this->createAppointment($salaA, $otroHorario);
        $turno = $this->createAppointment($salaA);

        Sanctum::actingAs($this->patient);

        $this->patchJson("/api/appointments/{$turno->id}/reschedule", ['scheduled_at' => $otroHorario])
            ->assertOk()
            ->assertJsonPath('appointment.sala_id', $salaB->id);
    }

    // --- GT-43: asignación manual ---

    public function test_secretaria_asigna_sala_manualmente(): void
    {
        $turno = $this->createAppointment(null);
        $sala = $this->createSala('D1', $this->derma);

        Sanctum::actingAs($this->secretary);

        $this->patchJson("/api/appointments/{$turno->id}/sala", ['sala_id' => $sala->id])
            ->assertOk()
            ->assertJsonPath('appointment.sala_id', $sala->id);
    }

    public function test_administrador_cambia_la_sala(): void
    {
        $salaA = $this->createSala('C1', $this->cardio);
        $salaB = $this->createSala('C2', $this->cardio);
        $turno = $this->createAppointment($salaA);

        Sanctum::actingAs($this->createUser(null, RoleName::Administrator));

        $this->patchJson("/api/appointments/{$turno->id}/sala", ['sala_id' => $salaB->id])
            ->assertOk()
            ->assertJsonPath('appointment.sala_id', $salaB->id);
    }

    public function test_no_se_asigna_una_sala_fuera_de_servicio(): void
    {
        $turno = $this->createAppointment(null);
        $sala = $this->createSala('C1', $this->cardio, 'fuera_de_servicio');

        Sanctum::actingAs($this->secretary);

        $this->patchJson("/api/appointments/{$turno->id}/sala", ['sala_id' => $sala->id])
            ->assertStatus(422);
    }

    public function test_paciente_y_medico_no_pueden_asignar_sala(): void
    {
        $turno = $this->createAppointment(null);
        $sala = $this->createSala('C1', $this->cardio);

        foreach ([$this->patient, $this->doctor] as $user) {
            Sanctum::actingAs($user);

            $this->patchJson("/api/appointments/{$turno->id}/sala", ['sala_id' => $sala->id])
                ->assertStatus(403);
            $this->getJson("/api/appointments/{$turno->id}/available-salas")
                ->assertStatus(403);
        }
    }

    public function test_paciente_no_puede_elegir_sala_al_reprogramar(): void
    {
        $turno = $this->createAppointment(null);
        $sala = $this->createSala('C1', $this->cardio);

        Sanctum::actingAs($this->patient);

        $this->patchJson("/api/appointments/{$turno->id}/reschedule", [
            'scheduled_at' => $this->slot,
            'sala_id' => $sala->id,
        ])->assertStatus(403);
    }

    public function test_salas_asignables_excluye_ocupadas_y_fuera_de_servicio(): void
    {
        $ocupada = $this->createSala('C1', $this->cardio);
        $this->createSala('C2', $this->cardio, 'fuera_de_servicio');
        $derma = $this->createSala('D1', $this->derma);
        $cardio = $this->createSala('C3', $this->cardio);
        $this->createAppointment($ocupada);
        $turno = $this->createAppointment(null);

        Sanctum::actingAs($this->secretary);

        $this->getJson("/api/appointments/{$turno->id}/available-salas")
            ->assertOk()
            ->assertJsonCount(2)
            // primero las de la especialidad del turno
            ->assertJsonPath('0.id', $cardio->id)
            ->assertJsonPath('0.matches_specialty', true)
            ->assertJsonPath('1.id', $derma->id)
            ->assertJsonPath('1.matches_specialty', false);
    }
}
