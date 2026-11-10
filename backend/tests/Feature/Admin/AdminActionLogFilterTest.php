<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminActionName;
use App\Enums\RoleName;
use App\Models\AdminActionLog;
use App\Models\Patient;
use App\Models\Role;
use Database\Seeders\AdminActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminActionLogFilterTest extends TestCase
{
    use RefreshDatabase;

    private Patient $superAdmin;
    private Patient $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, AdminActionSeeder::class]);

        $this->superAdmin = $this->crear(RoleName::SuperAdmin);
        $this->admin = $this->crear(RoleName::Administrator);
        Sanctum::actingAs($this->superAdmin);
    }

    private function crear(RoleName $rol): Patient
    {
        return Patient::factory()->create([
            'role_id' => Role::where('name', $rol->value)->first()->id,
        ]);
    }

    private function registrar(AdminActionName $accion): AdminActionLog
    {
        return AdminActionLog::record($this->superAdmin, $this->admin, $accion);
    }

    public function test_filtra_por_tipo_de_accion(): void
    {
        $concedida = $this->registrar(AdminActionName::GrantAdmin);
        $this->registrar(AdminActionName::RevokeAdmin);
        $otraConcedida = $this->registrar(AdminActionName::GrantAdmin);
        $this->registrar(AdminActionName::Deactivate);

        $response = $this->getJson('/api/auditoria?action=grant_admin')->assertOk();

        $this->assertEqualsCanonicalizing(
            [$concedida->id, $otraConcedida->id],
            collect($response->json('data'))->pluck('id')->all()
        );
        $response->assertJsonPath('meta.total', 2);
        foreach ($response->json('data') as $fila) {
            $this->assertSame('grant_admin', $fila['action']['name']);
        }
    }

    public function test_accion_sin_registros_devuelve_lista_vacia(): void
    {
        $this->registrar(AdminActionName::GrantAdmin);

        $this->getJson('/api/auditoria?action=grant_owner')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_sin_filtro_de_accion_devuelve_todos(): void
    {
        $this->registrar(AdminActionName::GrantAdmin);
        $this->registrar(AdminActionName::Activate);

        $this->getJson('/api/auditoria')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/auditoria?action=')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_accion_inexistente_responde_422(): void
    {
        $this->getJson('/api/auditoria?action=borrar_todo')
            ->assertStatus(422)
            ->assertJsonValidationErrors('action');
    }

    public function test_filtra_por_administrador_afectado(): void
    {
        $otroAdmin = $this->crear(RoleName::Administrator);
        $propio = $this->registrar(AdminActionName::GrantAdmin);
        $propio2 = $this->registrar(AdminActionName::Deactivate);
        AdminActionLog::record($this->superAdmin, $otroAdmin, AdminActionName::GrantAdmin);

        $response = $this->getJson('/api/auditoria?affected_admin_id=' . $this->admin->id)->assertOk();

        $this->assertEqualsCanonicalizing(
            [$propio->id, $propio2->id],
            collect($response->json('data'))->pluck('id')->all()
        );
        foreach ($response->json('data') as $fila) {
            $this->assertSame($this->admin->id, $fila['affected']['id']);
        }
    }

    public function test_administrador_sin_registros_devuelve_lista_vacia(): void
    {
        $sinHistorial = $this->crear(RoleName::Administrator);
        $this->registrar(AdminActionName::GrantAdmin);

        $this->getJson('/api/auditoria?affected_admin_id=' . $sinHistorial->id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_administrador_afectado_inexistente_responde_422(): void
    {
        $this->getJson('/api/auditoria?affected_admin_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('affected_admin_id');
    }

    private function registrarEn(string $fecha): AdminActionLog
    {
        $log = $this->registrar(AdminActionName::GrantAdmin);
        $log->update(['date' => $fecha]);

        return $log;
    }

    private function idsDe(string $query): array
    {
        return collect($this->getJson('/api/auditoria?' . $query)->assertOk()->json('data'))
            ->pluck('id')->all();
    }

    public function test_la_zona_horaria_de_la_aplicacion_es_buenos_aires(): void
    {
        $this->assertSame('America/Argentina/Buenos_Aires', config('app.timezone'));
    }

    public function test_rango_de_fechas_incluye_los_dias_limite_completos(): void
    {
        $antes = $this->registrarEn('2026-03-09 23:59:59');
        $desdeInicio = $this->registrarEn('2026-03-10 00:00:00');
        $medio = $this->registrarEn('2026-03-11 12:00:00');
        $hastaFin = $this->registrarEn('2026-03-12 23:59:59');
        $despues = $this->registrarEn('2026-03-13 00:00:00');

        $ids = $this->idsDe('date_from=2026-03-10&date_to=2026-03-12');

        $this->assertEqualsCanonicalizing([$desdeInicio->id, $medio->id, $hastaFin->id], $ids);
        $this->assertNotContains($antes->id, $ids);
        $this->assertNotContains($despues->id, $ids);
    }

    public function test_solo_desde_incluye_el_primer_instante_del_dia(): void
    {
        $antes = $this->registrarEn('2026-03-09 23:59:59');
        $inicio = $this->registrarEn('2026-03-10 00:00:00');
        $posterior = $this->registrarEn('2026-04-01 08:00:00');

        $ids = $this->idsDe('date_from=2026-03-10');

        $this->assertEqualsCanonicalizing([$inicio->id, $posterior->id], $ids);
        $this->assertNotContains($antes->id, $ids);
    }

    public function test_solo_hasta_incluye_hasta_las_2359_del_dia(): void
    {
        $anterior = $this->registrarEn('2026-01-01 08:00:00');
        $fin = $this->registrarEn('2026-03-10 23:59:59');
        $despues = $this->registrarEn('2026-03-11 00:00:00');

        $ids = $this->idsDe('date_to=2026-03-10');

        $this->assertEqualsCanonicalizing([$anterior->id, $fin->id], $ids);
        $this->assertNotContains($despues->id, $ids);
    }

    public function test_mismo_dia_en_desde_y_hasta_devuelve_todo_ese_dia(): void
    {
        $this->registrarEn('2026-03-09 23:59:59');
        $inicio = $this->registrarEn('2026-03-10 00:00:00');
        $fin = $this->registrarEn('2026-03-10 23:59:59');
        $this->registrarEn('2026-03-11 00:00:00');

        $this->assertEqualsCanonicalizing(
            [$inicio->id, $fin->id],
            $this->idsDe('date_from=2026-03-10&date_to=2026-03-10')
        );
    }
}
