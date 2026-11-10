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
use RuntimeException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminActionLogAtomicityTest extends TestCase
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

    public function test_conceder_se_revierte_si_falla_el_registro_en_el_log(): void
    {
        $target = $this->createUser(RoleName::OperationalUser);
        $originalRoleId = $target->role_id;
        AdminAction::where('name', AdminActionName::GrantAdmin->value)->delete();
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/users/{$target->id}/admin-role");

        $this->assertFalse($response->isSuccessful());
        $this->assertSame($originalRoleId, $target->fresh()->role_id);
        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_revocar_se_revierte_si_falla_el_registro_en_el_log(): void
    {
        $target = $this->createUser(RoleName::Administrator, ['is_owner' => true]);
        $originalRoleId = $target->role_id;
        $target->createToken('test');
        AdminAction::where('name', AdminActionName::RevokeAdmin->value)->delete();
        Sanctum::actingAs($this->superAdmin);

        $response = $this->deleteJson("/api/users/{$target->id}/admin-role");

        $this->assertFalse($response->isSuccessful());
        $fresh = $target->fresh();
        $this->assertSame($originalRoleId, $fresh->role_id);
        $this->assertTrue($fresh->is_owner);
        $this->assertSame(1, $fresh->tokens()->count());
        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_conceder_se_revierte_si_la_creacion_del_registro_lanza_excepcion(): void
    {
        $target = $this->createUser(RoleName::OperationalUser);
        $originalRoleId = $target->role_id;
        AdminActionLog::creating(function () {
            throw new RuntimeException('Fallo simulado al crear el registro.');
        });
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/users/{$target->id}/admin-role");

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame($originalRoleId, $target->fresh()->role_id);
        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_revocar_se_revierte_si_la_creacion_del_registro_lanza_excepcion(): void
    {
        $target = $this->createUser(RoleName::Administrator, ['is_owner' => true]);
        $originalRoleId = $target->role_id;
        $target->createToken('test');
        AdminActionLog::creating(function () {
            throw new RuntimeException('Fallo simulado al crear el registro.');
        });
        Sanctum::actingAs($this->superAdmin);

        $response = $this->deleteJson("/api/users/{$target->id}/admin-role");

        $this->assertSame(500, $response->getStatusCode());
        $fresh = $target->fresh();
        $this->assertSame($originalRoleId, $fresh->role_id);
        $this->assertTrue($fresh->is_owner);
        $this->assertSame(1, $fresh->tokens()->count());
        $this->assertDatabaseCount('admin_action_log', 0);
    }
}
