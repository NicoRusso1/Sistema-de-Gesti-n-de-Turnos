<?php

namespace Tests\Feature;

use App\Enums\AdminActionName;
use App\Enums\RoleName;
use App\Models\AdminAction;
use App\Models\Patient;
use App\Models\Role;
use Database\Seeders\AdminActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminActionLogScopeTest extends TestCase
{
    use RefreshDatabase;

    private Patient $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, AdminActionSeeder::class]);

        $this->admin = $this->createUser(RoleName::Administrator);
    }

    private function createUser(RoleName $role, array $attributes = []): Patient
    {
        return Patient::factory()->create(array_merge([
            'role_id' => Role::where('name', $role->value)->first()->id,
        ], $attributes));
    }

    public function test_dar_de_baja_un_usuario_operativo_no_registra_en_el_log(): void
    {
        $target = $this->createUser(RoleName::OperationalUser);
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/users/{$target->id}")->assertOk();

        $this->assertSame(0, $target->fresh()->status);
        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_reactivar_un_usuario_operativo_no_registra_en_el_log(): void
    {
        $target = $this->createUser(RoleName::OperationalUser, ['status' => 0]);
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/users/{$target->id}/restore")->assertOk();

        $this->assertSame(1, $target->fresh()->status);
        $this->assertDatabaseCount('admin_action_log', 0);
    }

    public function test_el_catalogo_contiene_todas_las_acciones(): void
    {
        foreach ([
            AdminActionName::Activate,
            AdminActionName::Deactivate,
            AdminActionName::GrantOwner,
            AdminActionName::RevokeOwner,
        ] as $action) {
            $this->assertDatabaseHas('admin_actions', ['name' => $action->value]);
        }

        $this->assertSame(count(AdminActionName::cases()), AdminAction::count());
    }
}
