<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\AppointmentState;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Room;
use App\Models\UserPermission;
use App\Models\UserType;
use Database\Seeders\AppointmentStateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppointmentStateSeeder::class);
    }

    protected function crearUsuario(
        string $rolNombre,
        ?string $userTypeNombre = null,
        array $permisos = []
    ): Patient {
        $rol = Role::firstOrCreate(['name' => $rolNombre]);
        $userType = $userTypeNombre
            ? UserType::firstOrCreate(['name' => $userTypeNombre])
            : null;

        $patient = Patient::create([
            'first_name' => 'Test',
            'last_name' => $rolNombre.($userTypeNombre ?? ''),
            'email' => strtolower($rolNombre.($userTypeNombre ?? '')).uniqid().'@test.com',
            'password_hash' => 'password',
            'role_id' => $rol->id,
            'user_type_id' => $userType?->id,
            'status' => 1,
        ]);

        if ($permisos) {
            UserPermission::create(array_merge(['user_id' => $patient->id], $permisos));
        }

        return $patient;
    }

    protected function crearSala(): Room
    {
        return Room::create(['number' => '101', 'floor' => '1', 'status' => 1]);
    }

    /** Un médico solo ve sus propios turnos, no los de otros médicos */
    public function test_medico_solo_ve_sus_propios_turnos(): void
    {
        $medico1 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value, [
            'view_own_appointments' => true,
        ]);
        $medico2 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value, [
            'view_own_appointments' => true,
        ]);
        $paciente = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $sala = $this->crearSala();
        $reserved = AppointmentState::where('name', 'reserved')->first();

        \App\Models\Appointment::create([
            'patient_id' => $paciente->id, 'doctor_id' => $medico1->id, 'room_id' => $sala->id,
            'date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:15',
            'duration' => 15, 'state_id' => $reserved->id,
        ]);
        \App\Models\Appointment::create([
            'patient_id' => $paciente->id, 'doctor_id' => $medico2->id, 'room_id' => $sala->id,
            'date' => now()->addDay()->toDateString(), 'start_time' => '10:00', 'end_time' => '10:15',
            'duration' => 15, 'state_id' => $reserved->id,
        ]);

        $response = $this->actingAs($medico1)->getJson('/api/appointments');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    /** Un paciente no puede ver turnos de otros pacientes */
    public function test_paciente_solo_ve_sus_propios_turnos(): void
    {
        $medico = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value);
        $paciente1 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $paciente2 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $sala = $this->crearSala();
        $reserved = AppointmentState::where('name', 'reserved')->first();

        \App\Models\Appointment::create([
            'patient_id' => $paciente1->id, 'doctor_id' => $medico->id, 'room_id' => $sala->id,
            'date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:15',
            'duration' => 15, 'state_id' => $reserved->id,
        ]);

        $response = $this->actingAs($paciente2)->getJson('/api/appointments');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    /** Una secretaria con view_all_appointments ve todos los turnos */
    public function test_secretaria_con_permiso_ve_todos_los_turnos(): void
    {
        $secretaria = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Secretary->value, [
            'view_all_appointments' => true,
        ]);
        $medico = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value);
        $paciente = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $sala = $this->crearSala();
        $reserved = AppointmentState::where('name', 'reserved')->first();

        \App\Models\Appointment::create([
            'patient_id' => $paciente->id, 'doctor_id' => $medico->id, 'room_id' => $sala->id,
            'date' => now()->addDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '09:15',
            'duration' => 15, 'state_id' => $reserved->id,
        ]);

        $response = $this->actingAs($secretaria)->getJson('/api/appointments');

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    /** Un paciente puede reservar un turno para sí mismo */
    public function test_paciente_puede_reservar_turno_para_si_mismo(): void
    {
        $paciente = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $medico = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value);
        $sala = $this->crearSala();

        $response = $this->actingAs($paciente)->postJson('/api/appointments', [
            'patient_id' => $paciente->id,
            'doctor_id' => $medico->id,
            'room_id' => $sala->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('appointments', ['patient_id' => $paciente->id, 'doctor_id' => $medico->id]);
    }

    /** Un paciente NO puede reservar un turno para otro paciente */
    public function test_paciente_no_puede_reservar_para_otro_paciente(): void
    {
        $paciente1 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $paciente2 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $medico = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value);
        $sala = $this->crearSala();

        $response = $this->actingAs($paciente1)->postJson('/api/appointments', [
            'patient_id' => $paciente2->id,
            'doctor_id' => $medico->id,
            'room_id' => $sala->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
        ]);

        $response->assertForbidden();
    }

    /** No se puede reservar fuera del horario de atención (08:00-20:00) */
    public function test_no_se_puede_reservar_fuera_del_horario_de_atencion(): void
    {
        $paciente = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $medico = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value);
        $sala = $this->crearSala();

        $response = $this->actingAs($paciente)->postJson('/api/appointments', [
            'patient_id' => $paciente->id,
            'doctor_id' => $medico->id,
            'room_id' => $sala->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '21:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('start_time');
    }

    /** No se pueden superponer dos turnos del mismo médico a la misma hora */
    public function test_no_se_puede_reservar_un_turno_superpuesto(): void
    {
        $paciente1 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $paciente2 = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $medico = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value);
        $sala = $this->crearSala();
        $reserved = AppointmentState::where('name', 'reserved')->first();
        $fecha = now()->addDay()->toDateString();

        \App\Models\Appointment::create([
            'patient_id' => $paciente1->id, 'doctor_id' => $medico->id, 'room_id' => $sala->id,
            'date' => $fecha, 'start_time' => '10:00', 'end_time' => '10:15',
            'duration' => 15, 'state_id' => $reserved->id,
        ]);

        $response = $this->actingAs($paciente2)->postJson('/api/appointments', [
            'patient_id' => $paciente2->id,
            'doctor_id' => $medico->id,
            'room_id' => $sala->id,
            'date' => $fecha,
            'start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('start_time');
    }

    /** No se puede reservar en el pasado */
    public function test_no_se_puede_reservar_en_el_pasado(): void
    {
        $paciente = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Patient->value);
        $medico = $this->crearUsuario(RoleName::OperationalUser->value, UserTypeName::Doctor->value);
        $sala = $this->crearSala();

        $response = $this->actingAs($paciente)->postJson('/api/appointments', [
            'patient_id' => $paciente->id,
            'doctor_id' => $medico->id,
            'room_id' => $sala->id,
            'date' => now()->subDay()->toDateString(),
            'start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('date');
    }
}
