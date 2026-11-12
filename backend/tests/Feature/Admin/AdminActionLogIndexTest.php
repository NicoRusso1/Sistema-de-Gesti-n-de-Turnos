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
use App\Models\AdminAction;

class AdminActionLogIndexTest extends TestCase
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
    }

    private function crear(RoleName $rol, array $attrs = []): Patient
    {
        return Patient::factory()->create($attrs + [
            'role_id' => Role::where('name', $rol->value)->first()->id,
        ]);
    }

        private function registrar(string $fecha, AdminActionName $accion = AdminActionName::GrantAdmin): AdminActionLog
    {
        return AdminActionLog::create([
            'superadmin_id' => $this->superAdmin->id,
            'affected_admin_id' => $this->admin->id,
            'action_id' => AdminAction::where('name', $accion->value)->firstOrFail()->id,
            'date' => $fecha,
        ]);
    }

    public function test_superadmin_lista_el_historial_con_la_estructura_esperada(): void
    {
        $this->registrar('2026-03-01 10:00:00');
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/auditoria')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'date',
                    'action' => ['name', 'label'],
                    'actor' => ['id', 'name'],
                    'affected' => ['id', 'name'],
                ]],
                'links',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('data.0.action.name', 'grant_admin')
            ->assertJsonPath('data.0.actor.id', $this->superAdmin->id)
            ->assertJsonPath('data.0.affected.id', $this->admin->id);
    }

    public function test_ordena_por_fecha_descendente_y_por_id_descendente_como_desempate(): void
    {
        $viejo = $this->registrar('2026-01-01 08:00:00');
        $empateA = $this->registrar('2026-02-01 08:00:00');
        $empateB = $this->registrar('2026-02-01 08:00:00');
        $nuevo = $this->registrar('2026-03-01 08:00:00');
        Sanctum::actingAs($this->superAdmin);

        $ids = collect($this->getJson('/api/auditoria')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$nuevo->id, $empateB->id, $empateA->id, $viejo->id], $ids);
    }

    public function test_pagina_los_resultados(): void
    {
        foreach (range(1, 5) as $i) {
            $this->registrar("2026-01-0{$i} 08:00:00");
        }
        Sanctum::actingAs($this->superAdmin);

        $pagina1 = $this->getJson('/api/auditoria?per_page=2&page=1')->assertOk();
        $pagina3 = $this->getJson('/api/auditoria?per_page=2&page=3')->assertOk();

        $pagina1->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 2);
        $pagina3->assertJsonCount(1, 'data')->assertJsonPath('meta.current_page', 3);
    }

    public function test_sin_registros_devuelve_lista_vacia(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/auditoria')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_un_administrador_propietario_no_accede(): void
    {
        Sanctum::actingAs($this->crear(RoleName::Administrator, ['is_owner' => true]));

        $this->getJson('/api/auditoria')->assertForbidden();
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/auditoria')->assertUnauthorized();
    }
}
