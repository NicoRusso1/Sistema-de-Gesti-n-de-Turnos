<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Models\Role;
use App\Models\Sala;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalMetricsTest extends TestCase
{
    use RefreshDatabase;

    private Patient $superAdmin;
    private Patient $administrator;
    private Patient $doctor;
    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $superAdminRole = Role::where('name', RoleName::SuperAdmin->value)->first();
        $adminRole = Role::where('name', RoleName::Administrator->value)->first();
        $userType = UserType::where('name', UserTypeName::Patient->value)->first();
        $doctorType = UserType::where('name', UserTypeName::Doctor->value)->first();

        $this->superAdmin = Patient::factory()->create([
            'role_id'      => $superAdminRole->id,
            'user_type_id' => $userType->id,
        ]);

        $this->administrator = Patient::factory()->create([
            'role_id'      => $adminRole->id,
            'user_type_id' => $userType->id,
        ]);

        $this->doctor = Patient::factory()->create([
            'role_id'      => $adminRole->id, // rol operativo
            'user_type_id' => $doctorType->id,
        ]);

        $this->patient = Patient::factory()->create([
            'user_type_id' => $userType->id,
        ]);
    }

    /**
     * Test 1: El SuperAdmin puede consultar las métricas agregadas.
     */
    public function test_superadmin_puede_consultar_metricas_globales(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/metricas/globales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'periodo' => ['desde', 'hasta', 'dias'],
                'kpis' => ['ingresos_totales', 'total_turnos', 'total_salas_activas', 'total_usuarios_act'],
                'turnos_por_especialidad',
                'ocupacion_salas',
                'usuarios_desglose',
            ]);
    }

    /**
     * Test 2: Un Administrator normal NO puede acceder (Criterio de Aceptación 4).
     */
    public function test_administrator_no_puede_acceder_a_metricas(): void
    {
        Sanctum::actingAs($this->administrator);

        $this->getJson('/api/metricas/globales')->assertStatus(403);
    }

    /**
     * Test 3: Médicos y Pacientes NO pueden acceder (Criterio de Aceptación 4).
     */
    public function test_medico_y_paciente_tienen_acceso_denegado(): void
    {
        Sanctum::actingAs($this->doctor);
        $this->getJson('/api/metricas/globales')->assertStatus(403);

        Sanctum::actingAs($this->patient);
        $this->getJson('/api/metricas/globales')->assertStatus(403);
    }

    /**
     * Test 4: Sin autenticación devuelve 401 Unauthorized.
     */
    public function test_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/metricas/globales')->assertStatus(401);
    }

    /**
     * Test 5: Exportación de PDF devuelve el archivo binario con cabeceras correctas.
     */
    public function test_exportacion_pdf_retorna_archivo(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/metricas/globales/export?formato=pdf');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Test 6: Exportación de CSV retorna archivo de texto tabulado.
     */
    public function test_exportacion_csv_retorna_stream(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/metricas/globales/export?formato=csv');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}