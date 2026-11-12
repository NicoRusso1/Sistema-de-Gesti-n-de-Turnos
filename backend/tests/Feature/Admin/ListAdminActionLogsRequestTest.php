<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\ListAdminActionLogsRequest;
use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ListAdminActionLogsRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validar(array $datos)
    {
        return Validator::make($datos, (new ListAdminActionLogsRequest())->rules());
    }

    public function test_sin_filtros_es_valido(): void
    {
        $this->assertFalse($this->validar([])->fails());
    }

    public function test_filtros_completos_validos(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Patient::factory()->create([
            'role_id' => Role::where('name', RoleName::Administrator->value)->first()->id,
        ]);

        $validator = $this->validar([
            'action' => 'grant_admin',
            'affected_admin_id' => $admin->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'per_page' => 20,
            'page' => 2,
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_accion_inexistente_es_invalida(): void
    {
        $this->assertTrue($this->validar(['action' => 'borrar_todo'])->errors()->has('action'));
    }

    public function test_administrador_afectado_inexistente_es_invalido(): void
    {
        $this->assertTrue($this->validar(['affected_admin_id' => 999999])->errors()->has('affected_admin_id'));
    }

    public function test_administrador_afectado_no_numerico_es_invalido(): void
    {
        $this->assertTrue($this->validar(['affected_admin_id' => 'abc'])->errors()->has('affected_admin_id'));
    }

    public function test_fecha_invalida(): void
    {
        $errors = $this->validar(['date_from' => 'no-es-fecha', 'date_to' => '2026-13-45'])->errors();

        $this->assertTrue($errors->has('date_from'));
        $this->assertTrue($errors->has('date_to'));
    }

    public function test_date_from_posterior_a_date_to_es_invalido(): void
    {
        $validator = $this->validar(['date_from' => '2026-02-10', 'date_to' => '2026-02-01']);

        $this->assertTrue($validator->errors()->has('date_to'));
    }

    public function test_date_from_igual_a_date_to_es_valido(): void
    {
        $this->assertFalse($this->validar(['date_from' => '2026-02-10', 'date_to' => '2026-02-10'])->fails());
    }

    public function test_per_page_excesivo_es_invalido(): void
    {
        $this->assertTrue($this->validar(['per_page' => ListAdminActionLogsRequest::MAX_PER_PAGE + 1])->errors()->has('per_page'));
    }

    public function test_per_page_en_el_maximo_es_valido(): void
    {
        $this->assertFalse($this->validar(['per_page' => ListAdminActionLogsRequest::MAX_PER_PAGE])->fails());
    }

    public function test_per_page_y_page_menores_a_uno_son_invalidos(): void
    {
        $errors = $this->validar(['per_page' => 0, 'page' => 0])->errors();

        $this->assertTrue($errors->has('per_page'));
        $this->assertTrue($errors->has('page'));
    }
}
