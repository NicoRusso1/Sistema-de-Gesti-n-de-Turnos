<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Sala;
use App\Models\Specialty;
use App\Models\UserPermission;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Matriz de permisos de los endpoints de turnos (seguimiento de GT-98 / GT-208).
 *
 * Cada test recorre todos los actores contra un endpoint y compara el status
 * esperado. El turno de prueba es del paciente "paciente" con el médico "medico":
 * "otro_paciente" y "otro_medico" no tienen nada que ver con él.
 */
class AppointmentPermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Patient> */
    private array $actors = [];
    private Specialty $specialty;
    private Sala $sala;
    private int $minuteOffset = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->specialty = Specialty::create(['name' => 'Cardiología']);
        $this->sala = Sala::create(['numero' => '101', 'piso' => 1, 'especialidad_id' => $this->specialty->id, 'estado' => 'disponible']);

        $this->actors = [
            'admin' => $this->createUser(null, RoleName::Administrator),
            'secretaria' => $this->createUser(UserTypeName::Secretary),
            'secretaria_sin_permisos' => $this->createUser(UserTypeName::Secretary, flags: [
                'view_all_appointments' => false, 'cancel_appointments' => false, 'edit_schedules' => false,
            ]),
            'medico' => $this->createUser(UserTypeName::Doctor),
            'medico_con_cancelar' => $this->createUser(UserTypeName::Doctor, flags: ['cancel_appointments' => true, 'edit_schedules' => true]),
            'otro_medico' => $this->createUser(UserTypeName::Doctor, flags: ['cancel_appointments' => true, 'edit_schedules' => true]),
            'medico_sin_permisos' => $this->createUser(UserTypeName::Doctor, flags: ['view_assigned_patients' => false]),
            'paciente' => $this->createUser(UserTypeName::Patient),
            'otro_paciente' => $this->createUser(UserTypeName::Patient),
            'paciente_sin_permisos' => $this->createUser(UserTypeName::Patient, flags: ['view_own_appointments' => false]),
        ];
    }

    private function createUser(?UserTypeName $type, RoleName $role = RoleName::OperationalUser, array $flags = []): Patient
    {
        $user = Patient::factory()->create([
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);

        if ($flags) {
            UserPermission::create(array_merge(
                ['patient_id' => $user->id],
                UserPermission::defaultsFor($type?->value),
                $flags
            ));
        }

        return $user;
    }

    // Turno nuevo para cada fila (cancelar/reprogramar lo modifican). Los
    // médicos "medico" y "medico_con_cancelar" son los asignados según el caso.
    private function appointmentFor(string $doctorKey = 'medico'): Appointment
    {
        return Appointment::create([
            'patient_id' => $this->actors['paciente']->id,
            'doctor_id' => $this->actors[$doctorKey]->id,
            'specialty_id' => $this->specialty->id,
            'scheduled_at' => $this->nextSlot(),
            'status' => 'reservado',
        ]);
    }

    private function nextSlot(): string
    {
        $this->minuteOffset += Appointment::DURATION_MINUTES;

        return now()->addDays(2)->setTime(8, 0)->addMinutes($this->minuteOffset)->format('Y-m-d H:i');
    }

    /**
     * @param  array<string, int>  $expected  actor => status HTTP esperado
     * @param  callable(string): \Illuminate\Testing\TestResponse  $request
     */
    private function assertMatrix(string $endpoint, array $expected, callable $request): void
    {
        foreach ($expected as $actorKey => $status) {
            Sanctum::actingAs($this->actors[$actorKey]);
            $response = $request($actorKey);

            $this->assertSame(
                $status,
                $response->status(),
                "{$endpoint} como '{$actorKey}': esperaba {$status} y devolvió {$response->status()} — " . $response->getContent()
            );
        }
    }

    public function test_listar_turnos(): void
    {
        $appointment = $this->appointmentFor();

        $this->assertMatrix('GET /api/appointments', [
            'admin' => 200,
            'secretaria' => 200,
            'secretaria_sin_permisos' => 403,
            'medico' => 200,
            'otro_medico' => 200,
            'medico_sin_permisos' => 403,
            'paciente' => 200,
            'otro_paciente' => 200,
            'paciente_sin_permisos' => 403,
        ], fn () => $this->getJson('/api/appointments'));

        // El filtro: médico y paciente ajenos reciben la lista vacía
        $visible = [
            'admin' => 1, 'secretaria' => 1, 'medico' => 1, 'paciente' => 1,
            'otro_medico' => 0, 'otro_paciente' => 0,
        ];
        foreach ($visible as $actorKey => $count) {
            Sanctum::actingAs($this->actors[$actorKey]);
            $this->getJson('/api/appointments')->assertJsonCount($count, 'data');
        }

        // Filtrar por patient_id / doctor_id no saltea el alcance
        Sanctum::actingAs($this->actors['otro_paciente']);
        $this->getJson("/api/appointments?patient_id={$appointment->patient_id}")->assertJsonCount(0, 'data');
        Sanctum::actingAs($this->actors['otro_medico']);
        $this->getJson("/api/appointments?doctor_id={$appointment->doctor_id}")->assertJsonCount(0, 'data');
    }

    public function test_reservar_turno(): void
    {
        $this->assertMatrix('POST /api/appointments', [
            'admin' => 201,
            'secretaria' => 403,
            'medico' => 403,
            'medico_sin_permisos' => 403,
            'paciente' => 201,
            'paciente_sin_permisos' => 403,
        ], fn () => $this->postJson('/api/appointments', [
            'doctor_id' => $this->actors['medico']->id,
            'specialty_id' => $this->specialty->id,
            'scheduled_at' => $this->nextSlot(),
            'monto_base' => 10000,
        ]));
    }

    public function test_cancelar_turno(): void
    {
        $this->assertMatrix('PATCH /api/appointments/{id}/cancel', [
            'admin' => 200,
            'secretaria' => 200,
            'secretaria_sin_permisos' => 403,
            'medico' => 403, // asignado pero sin cancel_appointments
            'medico_con_cancelar' => 200, // asignado y con cancel_appointments
            'otro_medico' => 403, // con cancel_appointments pero no es su turno
            'medico_sin_permisos' => 403,
            'paciente' => 200,
            'otro_paciente' => 403,
            'paciente_sin_permisos' => 403,
        ], function (string $actorKey) {
            $doctorKey = $actorKey === 'medico_con_cancelar' ? 'medico_con_cancelar' : 'medico';

            return $this->patchJson("/api/appointments/{$this->appointmentFor($doctorKey)->id}/cancel", ['reason' => 'Prueba']);
        });
    }

    public function test_reprogramar_turno(): void
    {
        $this->assertMatrix('PATCH /api/appointments/{id}/reschedule', [
            'admin' => 200,
            'secretaria' => 200,
            'secretaria_sin_permisos' => 403,
            'medico' => 403, // asignado pero sin edit_schedules
            'medico_con_cancelar' => 200, // asignado y con edit_schedules
            'otro_medico' => 403, // con edit_schedules pero no es su turno
            'medico_sin_permisos' => 403,
            'paciente' => 200,
            'otro_paciente' => 403,
            'paciente_sin_permisos' => 403,
        ], function (string $actorKey) {
            $doctorKey = $actorKey === 'medico_con_cancelar' ? 'medico_con_cancelar' : 'medico';
            $appointment = $this->appointmentFor($doctorKey);

            return $this->patchJson("/api/appointments/{$appointment->id}/reschedule", ['scheduled_at' => $this->nextSlot()]);
        });
    }

    public function test_salas_asignables(): void
    {
        $appointment = $this->appointmentFor();

        $this->assertMatrix('GET /api/appointments/{id}/available-salas', [
            'admin' => 200,
            'secretaria' => 200,
            'secretaria_sin_permisos' => 403,
            'medico' => 403,
            'otro_medico' => 403,
            'paciente' => 403,
            'otro_paciente' => 403,
        ], fn () => $this->getJson("/api/appointments/{$appointment->id}/available-salas"));
    }

    public function test_asignar_sala(): void
    {
        $this->assertMatrix('PATCH /api/appointments/{id}/sala', [
            'admin' => 200,
            'secretaria' => 200,
            'secretaria_sin_permisos' => 403,
            'medico' => 403,
            'otro_medico' => 403,
            'paciente' => 403,
            'otro_paciente' => 403,
        ], fn () => $this->patchJson("/api/appointments/{$this->appointmentFor()->id}/sala", ['sala_id' => $this->sala->id]));
    }

    public function test_pagar_turno(): void
    {
        // Solo los casos que se cortan antes de llegar a Mercado Pago: el
        // paciente titular sí pasaría y crearía una preferencia real
        $appointment = $this->appointmentFor();

        $this->assertMatrix('POST /api/appointments/{id}/payments', [
            'admin' => 403, // pasa el middleware pero no es el titular
            'secretaria' => 403,
            'medico' => 403,
            'otro_paciente' => 403,
            'paciente_sin_permisos' => 403,
        ], fn () => $this->postJson("/api/appointments/{$appointment->id}/payments"));

        $this->assertMatrix('POST /api/payments/{id}/verify', [
            'secretaria' => 403,
            'medico' => 403,
            'paciente_sin_permisos' => 403,
        ], fn () => $this->postJson('/api/payments/999/verify'));
    }

    public function test_sin_token_todo_devuelve_401(): void
    {
        $id = $this->appointmentFor()->id;

        $this->getJson('/api/appointments')->assertStatus(401);
        $this->postJson('/api/appointments')->assertStatus(401);
        $this->patchJson("/api/appointments/{$id}/cancel")->assertStatus(401);
        $this->patchJson("/api/appointments/{$id}/reschedule")->assertStatus(401);
        $this->getJson("/api/appointments/{$id}/available-salas")->assertStatus(401);
        $this->patchJson("/api/appointments/{$id}/sala")->assertStatus(401);
        $this->postJson("/api/appointments/{$id}/payments")->assertStatus(401);
    }
}
