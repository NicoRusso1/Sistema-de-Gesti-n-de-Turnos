<?php

namespace Tests\Feature;

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

class AdminActionLogRecordingTest extends TestCase
{
    use RefreshDatabase;

    private Patient $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, AdminActionSeeder::class]);

        $this->superAdmin = $this->createUser(RoleName::SuperAdmin);
    }

    private function createUser(RoleName $role, array $attributes = []): Patient
    {
        return Patient::factory()->create(array_merge([
            'role_id' => Role::where('name', $role->value)->first()->id,
        ], $attributes));
    }

    public function test_conceder_rol_administrador_registra_la_accion(): void
    {
        $target = $this->createUser(RoleName::OperationalUser);
        Sanctum::actingAs($this->superAdmin);

        $this->postJson("/api/users/{$target->id}/admin-role")->assertOk();

        $this->assertDatabaseCount('admin_action_log', 1);
        $log = AdminActionLog::with('action')->first();
        $this->assertSame($this->superAdmin->id, $log->superadmin_id);
        $this->assertSame($target->id, $log->affected_admin_id);
        $this->assertSame(AdminActionName::GrantAdmin->value, $log->action->name);
        $this->assertNotNull($log->date);
        $this->assertTrue(now()->diffInSeconds($log->date, true) < 60);
    }

    public function test_revocar_rol_administrador_registra_la_accion(): void
    {
        $target = $this->createUser(RoleName::Administrator);
        Sanctum::actingAs($this->superAdmin);

        $this->deleteJson("/api/users/{$target->id}/admin-role")->assertOk();

        $this->assertDatabaseCount('admin_action_log', 1);
        $log = AdminActionLog::with('action')->first();
        $this->assertSame($this->superAdmin->id, $log->superadmin_id);
        $this->assertSame($target->id, $log->affected_admin_id);
        $this->assertSame(AdminActionName::RevokeAdmin->value, $log->action->name);
        $this->assertNotNull($log->date);
        $this->assertTrue(now()->diffInSeconds($log->date, true) < 60);
    }

    public function test_un_propietario_tambien_queda_registrado_como_ejecutor(): void
    {
        $owner = $this->createUser(RoleName::Administrator, ['is_owner' => true]);
        $target = $this->createUser(RoleName::OperationalUser);
        Sanctum::actingAs($owner);

        $this->postJson("/api/users/{$target->id}/admin-role")->assertOk();

        $this->assertDatabaseHas('admin_action_log', [
            'superadmin_id' => $owner->id,
            'affected_admin_id' => $target->id,
        ]);
    }

    public function test_conceder_a_usuario_inexistente_no_registra_nada(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/users/9999/admin-role')->assertNotFound();

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_conceder_a_usuario_dado_de_baja_no_registra_nada(): void
    {
        $target = $this->createUser(RoleName::OperationalUser, ['status' => 0]);
        Sanctum::actingAs($this->superAdmin);

        $this->postJson("/api/users/{$target->id}/admin-role")->assertStatus(422);

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_conceder_a_quien_ya_es_administrador_no_registra_nada(): void
    {
        $target = $this->createUser(RoleName::Administrator);
        Sanctum::actingAs($this->superAdmin);

        $this->postJson("/api/users/{$target->id}/admin-role")->assertStatus(422);

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_conceder_sin_ser_superadmin_ni_propietario_no_registra_nada(): void
    {
        $admin = $this->createUser(RoleName::Administrator);
        $target = $this->createUser(RoleName::OperationalUser);
        Sanctum::actingAs($admin);

        $this->postJson("/api/users/{$target->id}/admin-role")->assertForbidden();

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_revocar_a_usuario_inexistente_no_registra_nada(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->deleteJson('/api/users/9999/admin-role')->assertNotFound();

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_revocar_a_quien_no_es_administrador_no_registra_nada(): void
    {
        $target = $this->createUser(RoleName::OperationalUser);
        Sanctum::actingAs($this->superAdmin);

        $this->deleteJson("/api/users/{$target->id}/admin-role")->assertStatus(422);

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_revocarse_a_si_mismo_no_registra_nada(): void
    {
        $owner = $this->createUser(RoleName::Administrator, ['is_owner' => true]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/users/{$owner->id}/admin-role")->assertStatus(422);

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_revocar_a_un_propietario_siendo_propietario_no_registra_nada(): void
    {
        $owner = $this->createUser(RoleName::Administrator, ['is_owner' => true]);
        $otherOwner = $this->createUser(RoleName::Administrator, ['is_owner' => true]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/users/{$otherOwner->id}/admin-role")->assertForbidden();

        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_revocar_sin_ser_superadmin_ni_propietario_no_registra_nada(): void
    {
        $admin = $this->createUser(RoleName::Administrator);
        $target = $this->createUser(RoleName::Administrator);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$target->id}/admin-role")->assertForbidden();

        $this->assertDatabaseCount('admin_action_log', 0);
    }
}
