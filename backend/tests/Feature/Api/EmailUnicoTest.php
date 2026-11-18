<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Laravel\Sanctum\Sanctum;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailUnicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);
    }

    private function datos(string $email): array
    {
        return [
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];
    }

    public function test_registro_rechaza_correo_repetido(): void
    {
        $this->postJson('/api/register', $this->datos('ana@test.com'))->assertCreated();

        $this->postJson('/api/register', $this->datos('ana@test.com'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, Patient::where('email', 'ana@test.com')->count());
    }

    public function test_registro_rechaza_correo_repetido_con_mayusculas(): void
    {
        $this->postJson('/api/register', $this->datos('ana@test.com'))->assertCreated();

        $this->postJson('/api/register', $this->datos('ANA@Test.com'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, Patient::count());
    }

    public function test_registro_rechaza_correo_repetido_con_espacios(): void
    {
        $this->postJson('/api/register', $this->datos('ana@test.com'))->assertCreated();

        $this->postJson('/api/register', $this->datos('  ana@test.com '))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, Patient::count());
    }

    public function test_registro_guarda_el_correo_normalizado(): void
    {
        $this->postJson('/api/register', $this->datos(' Ana@Test.COM '))->assertCreated();

        $this->assertDatabaseHas('patients', ['email' => 'ana@test.com']);
    }

    private function autenticarAdmin(): void
    {
        $admin = Patient::factory()->create([
            'role_id' => Role::where('name', RoleName::Administrator->value)->first()->id,
        ]);
        Sanctum::actingAs($admin);
    }

    public function test_admin_no_puede_crear_usuario_con_correo_repetido_en_mayusculas(): void
    {
        $this->autenticarAdmin();
        Patient::factory()->create(['email' => 'dup@test.com']);

        $this->postJson('/api/users', [
            'first_name' => 'Luis',
            'last_name' => 'Paz',
            'email' => 'DUP@Test.com',
            'user_type_id' => UserType::first()->id,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_admin_no_puede_editar_usuario_con_correo_ajeno_en_mayusculas(): void
    {
        $this->autenticarAdmin();
        Patient::factory()->create(['email' => 'dup@test.com']);
        $otro = Patient::factory()->create([
            'role_id' => Role::where('name', RoleName::OperationalUser->value)->first()->id,
            'user_type_id' => UserType::first()->id,
        ]);

        $this->putJson('/api/users/'.$otro->id, ['email' => 'DUP@test.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
