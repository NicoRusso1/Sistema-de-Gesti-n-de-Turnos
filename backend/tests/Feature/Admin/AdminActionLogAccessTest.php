<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminActionName;
use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\AdminActionLog;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Database\Seeders\AdminActionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminActionLogAccessTest extends TestCase
{
    use RefreshDatabase;

    private Patient $superAdmin;
    private Patient $afectado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, UserTypeSeeder::class, AdminActionSeeder::class]);

        $this->superAdmin = $this->crear(RoleName::SuperAdmin, null, ['first_name' => 'Superior', 'last_name' => 'Unico']);
        $this->afectado = $this->crear(RoleName::Administrator, null, ['first_name' => 'Afectada', 'last_name' => 'Secreta']);

        AdminActionLog::record($this->superAdmin, $this->afectado, AdminActionName::GrantAdmin);
    }

    private function crear(RoleName $rol, ?UserTypeName $tipo = null, array $attrs = []): Patient
    {
        return Patient::factory()->create($attrs + [
            'role_id' => Role::where('name', $rol->value)->first()->id,
            'user_type_id' => $tipo ? UserType::where('name', $tipo->value)->first()->id : null,
        ]);
    }

    public function test_sin_token_responde_401_sin_datos_del_log(): void
    {
        $response = $this->getJson('/api/auditoria')->assertUnauthorized();

        $response->assertJsonMissingPath('data');
        $this->assertStringNotContainsString('Afectada', $response->getContent());
    }

    public static function rolesSinAcceso(): array
    {
        return [
            'administrador' => [RoleName::Administrator, null],
            'médico' => [RoleName::OperationalUser, UserTypeName::Doctor],
            'paciente' => [RoleName::OperationalUser, UserTypeName::Patient],
            'secretario' => [RoleName::OperationalUser, UserTypeName::Secretary],
        ];
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_los_demas_roles_reciben_403_sin_datos_del_log(RoleName $rol, ?UserTypeName $tipo): void
    {
        Sanctum::actingAs($this->crear($rol, $tipo));

        $response = $this->getJson('/api/auditoria')
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo el Super Administrador puede realizar esta acción');

        $response->assertJsonMissingPath('data');
        $response->assertJsonMissingPath('meta');
        $contenido = $response->getContent();
        $this->assertStringNotContainsString('Afectada', $contenido);
        $this->assertStringNotContainsString('grant_admin', $contenido);
    }

    #[DataProvider('rolesSinAcceso')]
    public function test_los_demas_roles_reciben_403_tambien_con_filtros_validos(RoleName $rol, ?UserTypeName $tipo): void
    {
        Sanctum::actingAs($this->crear($rol, $tipo));

        $this->getJson('/api/auditoria?action=grant_admin&date_from=2020-01-01')->assertForbidden();
    }

    public function test_el_superadmin_accede_con_200_y_ve_el_registro(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/auditoria')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.affected.name', 'Afectada Secreta')
            ->assertJsonPath('data.0.actor.name', 'Superior Unico');
    }
}
