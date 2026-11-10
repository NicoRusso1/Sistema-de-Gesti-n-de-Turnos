<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminActionName;
use App\Enums\RoleName;
use App\Http\Resources\AdminActionLogResource;
use App\Models\AdminActionLog;
use App\Models\Patient;
use App\Models\Role;
use Database\Seeders\AdminActionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminActionLogResourceTest extends TestCase
{
    use RefreshDatabase;

    private function crearAdmin(string $nombre, string $apellido): Patient
    {
        return Patient::factory()->create([
            'first_name' => $nombre,
            'last_name' => $apellido,
            'role_id' => Role::where('name', RoleName::Administrator->value)->first()->id,
        ]);
    }

    private function resolver(AdminActionLog $log): array
    {
        $log->load(['action', 'superAdmin', 'affectedAdmin']);

        return (new AdminActionLogResource($log))->toArray(Request::create('/'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, AdminActionSeeder::class]);
    }

    public function test_expone_id_fecha_accion_ejecutor_y_afectado(): void
    {
        $ejecutor = $this->crearAdmin('Ana', 'Lopez');
        $afectado = $this->crearAdmin('Beto', 'Ruiz');
        $log = AdminActionLog::record($ejecutor, $afectado, AdminActionName::GrantAdmin);

        $data = $this->resolver($log);

        $this->assertSame($log->id, $data['id']);
        $this->assertSame($log->date->toIso8601String(), $data['date']);
        $this->assertSame(['name' => 'grant_admin', 'label' => 'Otorgó rol de administrador'], $data['action']);
        $this->assertSame(['id' => $ejecutor->id, 'name' => 'Ana Lopez'], $data['actor']);
        $this->assertSame(['id' => $afectado->id, 'name' => 'Beto Ruiz'], $data['affected']);
    }

    public function test_cada_accion_tiene_etiqueta_en_espanol(): void
    {
        foreach (AdminActionName::cases() as $case) {
            $this->assertNotSame($case->value, $case->label());
            $this->assertNotEmpty($case->label());
        }
    }
}
