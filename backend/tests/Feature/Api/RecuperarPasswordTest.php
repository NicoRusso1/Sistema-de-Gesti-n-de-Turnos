<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecuperarPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_si_falla_el_envio_responde_503_con_mensaje_claro(): void
    {
        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);
        Patient::factory()->create(['email' => 'ana@test.com', 'status' => 1]);
        Mail::shouldReceive('html')->andThrow(new \RuntimeException('smtp caido'));

        $this->postJson('/api/forgot-password', ['email' => 'ana@test.com'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'No pudimos enviar el correo de recuperación. Intentá de nuevo más tarde.');
    }

    public function test_enlace_usa_la_url_del_frontend(): void
    {
        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);
        config(['app.frontend_url' => 'https://front.test']);
        Patient::factory()->create(['email' => 'ana@test.com', 'status' => 1]);
        $html = '';
        Mail::shouldReceive('html')->once()->andReturnUsing(function ($contenido) use (&$html) {
            $html = $contenido;
        });

        $this->postJson('/api/forgot-password', ['email' => 'ana@test.com'])->assertOk();

        $this->assertStringContainsString('https://front.test/reset-password?token=', $html);
    }
}
