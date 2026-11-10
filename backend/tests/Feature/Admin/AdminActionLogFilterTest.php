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

    private function registrarCon(Patient $afectado, AdminActionName $accion, string $fecha): AdminActionLog
    {
        $log = AdminActionLog::record($this->superAdmin, $afectado, $accion);
        $log->update(['date' => $fecha]);

        return $log;
    }

    public function test_filtros_combinados_accion_administrador_y_fechas(): void
    {
        $otro = $this->crear(RoleName::Administrator);

        $coincide = $this->registrarCon($this->admin, AdminActionName::GrantAdmin, '2026-03-11 09:00:00');
        $coincideLimite = $this->registrarCon($this->admin, AdminActionName::GrantAdmin, '2026-03-12 23:59:59');
        $this->registrarCon($this->admin, AdminActionName::RevokeAdmin, '2026-03-11 09:00:00'); // otra acción
        $this->registrarCon($otro, AdminActionName::GrantAdmin, '2026-03-11 09:00:00'); // otro administrador
        $this->registrarCon($this->admin, AdminActionName::GrantAdmin, '2026-03-09 23:59:59'); // antes del rango
        $this->registrarCon($this->admin, AdminActionName::GrantAdmin, '2026-03-13 00:00:00'); // después del rango

        $query = http_build_query([
            'action' => 'grant_admin',
            'affected_admin_id' => $this->admin->id,
            'date_from' => '2026-03-10',
            'date_to' => '2026-03-12',
        ]);

        $this->assertEqualsCanonicalizing([$coincide->id, $coincideLimite->id], $this->idsDe($query));
    }

    public function test_filtros_combinados_sin_coincidencias_devuelven_lista_vacia(): void
    {
        $this->registrarCon($this->admin, AdminActionName::GrantAdmin, '2026-03-11 09:00:00');

        $this->getJson('/api/auditoria?action=revoke_admin&affected_admin_id=' . $this->admin->id . '&date_from=2026-03-01')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_paginacion_funciona_junto_con_los_filtros(): void
    {
        $otro = $this->crear(RoleName::Administrator);
        $esperados = [];
        foreach (range(1, 5) as $i) {
            $esperados[] = $this->registrarCon($this->admin, AdminActionName::Activate, "2026-03-0{$i} 10:00:00")->id;
        }
        $this->registrarCon($this->admin, AdminActionName::Deactivate, '2026-03-03 10:00:00');
        $this->registrarCon($otro, AdminActionName::Activate, '2026-03-03 10:00:00');

        $base = '/api/auditoria?action=activate&affected_admin_id=' . $this->admin->id . '&date_from=2026-03-01&date_to=2026-03-31&per_page=2';

        $pagina1 = $this->getJson($base . '&page=1')->assertOk()
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonCount(2, 'data');
        $pagina3 = $this->getJson($base . '&page=3')->assertOk()->assertJsonCount(1, 'data');

        // Orden descendente por fecha: la página 1 trae los dos más recientes y la 3 el más antiguo.
        $this->assertSame([$esperados[4], $esperados[3]], collect($pagina1->json('data'))->pluck('id')->all());
        $this->assertSame([$esperados[0]], collect($pagina3->json('data'))->pluck('id')->all());
    }

    public function test_fecha_invalida_responde_422_en_el_campo_correcto(): void
    {
        $this->getJson('/api/auditoria?date_from=no-es-fecha')
            ->assertStatus(422)->assertJsonValidationErrors('date_from');

        $this->getJson('/api/auditoria?date_to=2026-13-45')
            ->assertStatus(422)->assertJsonValidationErrors('date_to');
    }

    public function test_date_from_posterior_a_date_to_responde_422_en_date_to(): void
    {
        $this->getJson('/api/auditoria?date_from=2026-03-10&date_to=2026-03-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_to')
            ->assertJsonMissingValidationErrors('date_from');
    }

    public function test_per_page_fuera_de_rango_responde_422_en_per_page(): void
    {
        foreach (['0', '101', '-5', 'abc'] as $valor) {
            $this->getJson('/api/auditoria?per_page=' . $valor)
                ->assertStatus(422)
                ->assertJsonValidationErrors('per_page');
        }
    }

    public function test_per_page_en_el_maximo_es_aceptado(): void
    {
        $this->getJson('/api/auditoria?per_page=100')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_page_invalida_responde_422_en_page(): void
    {
        $this->getJson('/api/auditoria?page=0')
            ->assertStatus(422)->assertJsonValidationErrors('page');
    }

    public function test_varios_errores_se_informan_a_la_vez_en_sus_campos(): void
    {
        $this->getJson('/api/auditoria?action=nada&affected_admin_id=999999&per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action', 'affected_admin_id', 'per_page']);
    }
}
