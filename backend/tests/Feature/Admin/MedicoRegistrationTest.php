<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicoRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guests_cannot_access_the_medico_registration_form(): void
    {
        $response = $this->get('/admin/medicos/crear');

        $response->assertRedirect('/login');
    }

    public function test_a_usuario_cannot_register_a_medico(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RoleName::Usuario->value);

        $this->actingAs($usuario)
            ->get('/admin/medicos/crear')
            ->assertForbidden();

        $this->actingAs($usuario)
            ->post('/admin/medicos', [
                'name' => 'Dr. Nadie',
                'email' => 'nadie@hospital.test',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'nadie@hospital.test']);
    }

    public function test_an_administrador_can_register_a_medico(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Administrador->value);

        $response = $this->actingAs($admin)->post('/admin/medicos', [
            'name' => 'Dra. Ana Perez',
            'email' => 'ana.perez@hospital.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('admin.medicos.create'));

        $medico = User::whereEmail('ana.perez@hospital.test')->firstOrFail();
        $this->assertTrue($medico->hasRole(RoleName::Medico->value));
    }
}
