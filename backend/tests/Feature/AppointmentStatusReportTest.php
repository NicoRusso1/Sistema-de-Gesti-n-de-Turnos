<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GT-67: reporte de turnos por estado.
 */
class AppointmentStatusReportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/reportes/turnos-por-estado';

    private Patient $patient;
    private Patient $doctor;
    private Patient $otherDoctor;
    private Patient $admin;
    private Patient $superAdmin;
    private Specialty $clinica;
    private Specialty $pediatria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient);
        $this->doctor = $this->createUser(UserTypeName::Doctor);
        $this->otherDoctor = $this->createUser(UserTypeName::Doctor);
        $this->admin = $this->createUser(null, RoleName::Administrator);
        $this->superAdmin = $this->createUser(null, RoleName::SuperAdmin);
        $this->clinica = Specialty::create(['name' => 'Clínica Médica']);
        $this->pediatria = Specialty::create(['name' => 'Pediatría']);
    }

    private function createUser(?UserTypeName $type, RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create([
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    private function turno(string $status, array $attributes = []): Appointment
    {
        return Appointment::create($attributes + [
            'patient_id'   => $this->patient->id,
            'doctor_id'    => $this->doctor->id,
            'specialty_id' => $this->clinica->id,
            'scheduled_at' => now(),
            'status'       => $status,
        ]);
    }

    private function cantidades(array $estados): array
    {
        return array_column($estados, 'cantidad', 'estado');
    }

    public function test_muestra_totales_por_cada_estado_con_descripcion(): void
    {
        $this->turno('reservado');
        $this->turno('reservado');
        $this->turno('abonado');
        $this->turno('en_espera');
        $this->turno('cancelado');
        $this->turno(Appointment::STATUS_COMPLETED);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson(self::URL)->assertOk()->assertJsonPath('total', 6);

        $this->assertSame([
            'reservado'  => 2,
            'abonado'    => 1,
            'en_espera'  => 1,
            'completado' => 1,
            'cancelado'  => 1,
        ], $this->cantidades($response->json('estados')));

        foreach ($response->json('estados') as $estado) {
            $this->assertNotEmpty($estado['etiqueta']);
            $this->assertNotEmpty($estado['descripcion']);
        }

        $response->assertJsonPath('estados.0.porcentaje', 33.3)
            ->assertJsonStructure(['generado_en', 'filtros', 'opciones' => ['especialidades', 'medicos']]);
    }

    public function test_estados_sin_turnos_aparecen_en_cero(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson(self::URL)->assertOk()->assertJsonPath('total', 0);

        $this->assertCount(5, $response->json('estados'));
        $this->assertSame([0, 0, 0, 0, 0], array_values($this->cantidades($response->json('estados'))));
    }

    public function test_estados_heredados_se_suman_a_su_equivalente(): void
    {
        $this->turno('scheduled');
        $this->turno('confirmed');
        $this->turno('cancelled');

        Sanctum::actingAs($this->admin);

        $cantidades = $this->cantidades($this->getJson(self::URL)->assertOk()->json('estados'));

        $this->assertSame(1, $cantidades['reservado']);
        $this->assertSame(1, $cantidades['abonado']);
        $this->assertSame(1, $cantidades['cancelado']);
    }

    public function test_filtra_por_rango_de_fechas_con_dias_completos(): void
    {
        $this->turno('reservado', ['scheduled_at' => '2026-11-01 08:00:00']);
        $this->turno('reservado', ['scheduled_at' => '2026-11-05 23:45:00']);
        $this->turno('reservado', ['scheduled_at' => '2026-11-06 00:00:00']);
        $this->turno('reservado', ['scheduled_at' => '2026-10-31 23:59:00']);

        Sanctum::actingAs($this->admin);

        $this->getJson(self::URL . '?fecha_desde=2026-11-01&fecha_hasta=2026-11-05')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('filtros.fecha_desde', '2026-11-01')
            ->assertJsonPath('filtros.fecha_hasta', '2026-11-05');
    }

    public function test_filtra_por_especialidad(): void
    {
        $this->turno('abonado');
        $this->turno('abonado', ['specialty_id' => $this->pediatria->id]);

        Sanctum::actingAs($this->admin);

        $this->getJson(self::URL . "?specialty_id={$this->pediatria->id}")
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_filtra_por_medico(): void
    {
        $this->turno('abonado');
        $this->turno('cancelado', ['doctor_id' => $this->otherDoctor->id]);
        $this->turno('reservado', ['doctor_id' => $this->otherDoctor->id]);

        Sanctum::actingAs($this->admin);

        $cantidades = $this->cantidades(
            $this->getJson(self::URL . "?doctor_id={$this->otherDoctor->id}")
                ->assertOk()->assertJsonPath('total', 2)->json('estados')
        );

        $this->assertSame(0, $cantidades['abonado']);
        $this->assertSame(1, $cantidades['cancelado']);
    }

    public function test_combina_filtros(): void
    {
        $this->turno('abonado', ['doctor_id' => $this->otherDoctor->id, 'specialty_id' => $this->pediatria->id, 'scheduled_at' => '2026-11-02 10:00:00']);
        $this->turno('abonado', ['doctor_id' => $this->otherDoctor->id, 'specialty_id' => $this->pediatria->id, 'scheduled_at' => '2026-12-02 10:00:00']);
        $this->turno('abonado', ['doctor_id' => $this->otherDoctor->id, 'scheduled_at' => '2026-11-02 10:00:00']);

        Sanctum::actingAs($this->admin);

        $this->getJson(self::URL . "?fecha_desde=2026-11-01&fecha_hasta=2026-11-30&specialty_id={$this->pediatria->id}&doctor_id={$this->otherDoctor->id}")
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_rechaza_rango_de_fechas_invertido_y_filtros_inexistentes(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson(self::URL . '?fecha_desde=2026-11-10&fecha_hasta=2026-11-01')
            ->assertStatus(422)->assertJsonValidationErrors('fecha_hasta');

        $this->getJson(self::URL . '?specialty_id=9999&doctor_id=9999')
            ->assertStatus(422)->assertJsonValidationErrors(['specialty_id', 'doctor_id']);
    }

    public function test_super_admin_puede_acceder(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson(self::URL)->assertOk();
    }

    public function test_paciente_y_medico_no_pueden_acceder(): void
    {
        Sanctum::actingAs($this->patient);
        $this->getJson(self::URL)->assertStatus(403);

        Sanctum::actingAs($this->doctor);
        $this->getJson(self::URL)->assertStatus(403);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
    }
}
