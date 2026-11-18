<?php

namespace Tests\Feature;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelegramReminderTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://n8n.test/webhook/turnos-recordatorio';

    private Patient $patient;
    private Patient $doctor;
    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        config([
            'services.n8n.reminder_webhook_url' => self::WEBHOOK,
            'services.n8n.secret' => 'secreto-test',
            'services.n8n.telegram_bot_username' => 'SigtTurnosBot',
        ]);

        $this->patient = $this->createUser(UserTypeName::Patient, ['first_name' => 'Lucia', 'last_name' => 'Gomez']);
        $this->doctor = $this->createUser(UserTypeName::Doctor, ['first_name' => 'Ana', 'last_name' => 'Perez']);
        $this->specialty = Specialty::create(['name' => 'Cardiología']);
    }

    private function createUser(UserTypeName $type, array $attributes = []): Patient
    {
        return Patient::factory()->create($attributes + [
            'role_id' => Role::where('name', RoleName::OperationalUser->value)->first()->id,
            'user_type_id' => UserType::where('name', $type->value)->first()->id,
        ]);
    }

    private function createAppointment(): Appointment
    {
        return Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => $this->specialty->id,
            'scheduled_at' => now()->addHours(5),
            'status' => 'reservado',
        ]);
    }

    private function linkTelegram(string $chatId = '123456789'): void
    {
        $this->patient->telegram_chat_id = $chatId;
        $this->patient->save();
    }

    public function test_recordatorio_se_envia_a_n8n_si_el_paciente_tiene_telegram(): void
    {
        Mail::fake();
        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);
        $this->linkTelegram();
        $appointment = $this->createAppointment();

        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) use ($appointment) {
            return $request->url() === self::WEBHOOK
                && $request->hasHeader('X-N8N-Secret', 'secreto-test')
                && $request['chat_id'] === '123456789'
                && $request['appointment_id'] === $appointment->id
                && str_contains($request['texto'], 'Dr/a. Ana Perez')
                && str_contains($request['texto'], 'Cardiología');
        });
        Mail::assertSent(\App\Mail\RecordatorioTurnoMail::class, 1);
    }

    public function test_no_se_llama_a_n8n_si_el_paciente_no_vinculo_telegram(): void
    {
        Mail::fake();
        Http::fake();
        $this->createAppointment();

        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();

        Http::assertNothingSent();
        Mail::assertSent(\App\Mail\RecordatorioTurnoMail::class, 1);
    }

    public function test_no_se_llama_a_n8n_si_no_esta_configurado(): void
    {
        config(['services.n8n.reminder_webhook_url' => null]);
        Mail::fake();
        Http::fake();
        $this->linkTelegram();
        $this->createAppointment();

        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_si_n8n_falla_el_recordatorio_por_email_queda_enviado(): void
    {
        Mail::fake();
        Http::fake([self::WEBHOOK => Http::response('error', 500)]);
        $this->linkTelegram();
        $this->createAppointment();

        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();

        $this->assertSame(NotificationDeliveryStatus::Sent, Notification::first()->estado_envio);
    }

    public function test_si_falla_el_email_telegram_se_manda_igual_y_no_se_repite_al_reintentar(): void
    {
        Http::fake([self::WEBHOOK => Http::response(['ok' => true])]);
        $this->linkTelegram();
        $this->createAppointment();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP caído'));
        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();

        Http::assertSentCount(1);
        $fallida = Notification::first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $fallida->estado_envio);
        $this->assertNotNull($fallida->telegram_enviado_at);

        // El cron reintenta el email: sale el email pero Telegram no se repite
        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        Mail::fake();
        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();

        Http::assertSentCount(1);
        Mail::assertSent(\App\Mail\RecordatorioTurnoMail::class, 1);
    }

    public function test_si_n8n_falla_se_reintenta_telegram_en_el_proximo_intento(): void
    {
        Http::fakeSequence(self::WEBHOOK)->push('error', 500)->push(['ok' => true]);
        $this->linkTelegram();
        $appointment = $this->createAppointment();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP caído'));
        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();
        $this->assertNull(Notification::first()->telegram_enviado_at);

        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        Mail::fake();
        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();

        Http::assertSentCount(2);
        $this->assertSame(1, Notification::where('appointment_id', $appointment->id)->whereNotNull('telegram_enviado_at')->count());
    }

    public function test_otras_notificaciones_no_usan_telegram(): void
    {
        Mail::fake();
        Http::fake();
        $this->linkTelegram();
        $appointment = $this->createAppointment();

        $service = app(\App\Services\AppointmentNotificationService::class);
        $service->notifyCancellation($appointment, 'Motivo');
        $service->notifyRescheduling($appointment, '01/01/2027 10:00');

        Http::assertNothingSent();
    }

    public function test_flujo_de_vinculacion_completo(): void
    {
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/me/telegram')
            ->assertOk()
            ->assertJson(['linked' => false, 'available' => true]);

        $url = $this->postJson('/api/me/telegram/link')->assertOk()->json('url');
        $this->assertStringStartsWith('https://t.me/SigtTurnosBot?start=', $url);
        $token = substr($url, strlen('https://t.me/SigtTurnosBot?start='));

        $this->postJson('/api/integrations/n8n/telegram/link', ['token' => $token, 'chat_id' => 987654321], ['X-N8N-Secret' => 'secreto-test'])
            ->assertOk()
            ->assertJson(['first_name' => 'Lucia']);

        $this->patient->refresh();
        $this->assertSame('987654321', $this->patient->telegram_chat_id);
        $this->assertNull($this->patient->telegram_link_token);

        $this->getJson('/api/me/telegram')->assertJson(['linked' => true]);

        // El token es de un solo uso
        $this->postJson('/api/integrations/n8n/telegram/link', ['token' => $token, 'chat_id' => '1'], ['X-N8N-Secret' => 'secreto-test'])
            ->assertNotFound();

        $this->deleteJson('/api/me/telegram')->assertOk();
        $this->assertNull($this->patient->fresh()->telegram_chat_id);
    }

    public function test_confirmar_vinculacion_requiere_el_secreto_de_n8n(): void
    {
        $this->patient->telegram_link_token = 'token-valido';
        $this->patient->save();

        $this->postJson('/api/integrations/n8n/telegram/link', ['token' => 'token-valido', 'chat_id' => '1'])
            ->assertUnauthorized();

        $this->postJson('/api/integrations/n8n/telegram/link', ['token' => 'token-valido', 'chat_id' => '1'], ['X-N8N-Secret' => 'otro'])
            ->assertUnauthorized();

        $this->assertNull($this->patient->fresh()->telegram_chat_id);
    }

    public function test_link_no_disponible_si_falta_el_bot(): void
    {
        config(['services.n8n.telegram_bot_username' => null]);
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/me/telegram')->assertJson(['available' => false]);
        $this->postJson('/api/me/telegram/link')->assertStatus(503);
    }

    public function test_datos_de_telegram_no_se_exponen_en_me(): void
    {
        $this->linkTelegram();
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/me')->assertJsonMissingPath('telegram_chat_id')->assertJsonMissingPath('user.telegram_chat_id');
    }
}
