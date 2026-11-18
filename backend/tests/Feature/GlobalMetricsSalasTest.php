<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Sala;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalMetricsSalasTest extends TestCase
{
    use RefreshDatabase;

    private Patient $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->superAdmin = Patient::factory()->create([
            'role_id'      => Role::where('name', RoleName::SuperAdmin->value)->first()->id,
            'user_type_id' => UserType::where('name', UserTypeName::Patient->value)->first()->id,
        ]);
    }

    public function test_cuenta_solo_las_salas_disponibles_como_activas(): void
    {
        Sala::create(['numero' => '101', 'piso' => 1, 'estado' => 'disponible']);
        Sala::create(['numero' => '102', 'piso' => 1, 'estado' => 'disponible']);
        Sala::create(['numero' => '103', 'piso' => 1, 'estado' => 'fuera_de_servicio']);

        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/metricas/globales')
            ->assertOk()
            ->assertJsonPath('kpis.total_salas_activas', 2);
    }

    public function test_la_ocupacion_marca_cada_sala_segun_su_estado(): void
    {
        Sala::create(['numero' => '201', 'piso' => 2, 'estado' => 'disponible']);
        Sala::create(['numero' => '202', 'piso' => 2, 'estado' => 'fuera_de_servicio']);

        Sanctum::actingAs($this->superAdmin);

        $estados = collect($this->getJson('/api/metricas/globales')->assertOk()->json('ocupacion_salas'))
            ->pluck('estado', 'numero');

        $this->assertSame('Activa', $estados['201']);
        $this->assertSame('Inactiva', $estados['202']);
    }
}
