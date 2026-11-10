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
}
