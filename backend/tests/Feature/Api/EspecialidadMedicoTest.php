<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EspecialidadMedicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambiar_y_volver_a_la_misma_especialidad_mantiene_al_medico_listado(): void
    {
        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);
        $admin = Patient::factory()->create([
            'role_id' => Role::where('name', RoleName::Administrator->value)->first()->id,
        ]);
        $medico = Patient::factory()->create([
            'role_id' => Role::where('name', RoleName::OperationalUser->value)->first()->id,
            'user_type_id' => UserType::where('name', UserTypeName::Doctor->value)->first()->id,
            'status' => 1,
        ]);
        $a = Specialty::create(['name' => 'Cardiologia', 'status' => 1]);
        $b = Specialty::create(['name' => 'Pediatria', 'status' => 1]);
        Sanctum::actingAs($admin);

        $this->putJson("/api/doctors/{$medico->id}/specialties", ['specialty_ids' => [$a->id]])->assertOk();
        $this->putJson("/api/doctors/{$medico->id}/specialties", ['specialty_ids' => [$b->id]])->assertOk();
        $this->putJson("/api/doctors/{$medico->id}/specialties", ['specialty_ids' => [$a->id]])->assertOk();

        $this->getJson("/api/specialties/{$a->id}/doctors")->assertOk()->assertJsonCount(1)
            ->assertJsonFragment(['id' => $medico->id]);
        $this->getJson("/api/specialties/{$b->id}/doctors")->assertOk()->assertJsonCount(0);
    }
}
