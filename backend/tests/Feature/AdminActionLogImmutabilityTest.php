<?php

namespace Tests\Feature;

use App\Enums\AdminActionName;
use App\Enums\RoleName;
use App\Models\AdminAction;
use App\Models\AdminActionLog;
use App\Models\Patient;
use App\Models\Role;
use Database\Seeders\AdminActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AdminActionLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private Patient $actor;
    private Patient $affected;
    private AdminActionLog $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, AdminActionSeeder::class]);

        $this->actor = Patient::factory()->create([
            'role_id' => Role::where('name', RoleName::SuperAdmin->value)->first()->id,
        ]);
        $this->affected = Patient::factory()->create([
            'role_id' => Role::where('name', RoleName::Administrator->value)->first()->id,
        ]);

        $this->log = AdminActionLog::record($this->actor, $this->affected, AdminActionName::GrantAdmin);
    }

    public function test_se_pueden_crear_registros_nuevos(): void
    {
        $second = AdminActionLog::record($this->actor, $this->affected, AdminActionName::RevokeAdmin);

        $this->assertDatabaseCount('admin_action_log', 2);
        $this->assertSame($this->actor->id, $second->superadmin_id);
        $this->assertSame($this->affected->id, $second->affected_admin_id);
    }

    public function test_no_se_puede_modificar_un_registro_con_update(): void
    {
        $otherAction = AdminAction::where('name', AdminActionName::RevokeAdmin->value)->first();

        try {
            $this->log->update(['action_id' => $otherAction->id]);
            $this->fail('Se esperaba una excepción al modificar el registro.');
        } catch (LogicException $e) {
            $this->assertSame('Los registros del log de acciones administrativas no pueden modificarse.', $e->getMessage());
        }

        $this->assertNotEquals($otherAction->id, $this->log->fresh()->action_id);
    }

    public function test_no_se_puede_modificar_un_registro_con_save(): void
    {
        $this->log->affected_admin_id = $this->actor->id;

        try {
            $this->log->save();
            $this->fail('Se esperaba una excepción al guardar cambios sobre el registro.');
        } catch (LogicException $e) {
            $this->assertSame('Los registros del log de acciones administrativas no pueden modificarse.', $e->getMessage());
        }

        $this->assertSame($this->affected->id, $this->log->fresh()->affected_admin_id);
    }

    public function test_no_se_puede_eliminar_un_registro(): void
    {
        try {
            $this->log->delete();
            $this->fail('Se esperaba una excepción al eliminar el registro.');
        } catch (LogicException $e) {
            $this->assertSame('Los registros del log de acciones administrativas no pueden eliminarse.', $e->getMessage());
        }

        $this->assertDatabaseHas('admin_action_log', ['id' => $this->log->id]);
    }

    public function test_no_existen_rutas_que_modifiquen_o_eliminen_el_log(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertStringNotContainsString('admin-action-log', $route->uri());
            $this->assertStringNotContainsString('admin_action_log', $route->uri());
            $this->assertStringNotContainsString('AdminActionLog', $route->getActionName());
        }
    }
}
