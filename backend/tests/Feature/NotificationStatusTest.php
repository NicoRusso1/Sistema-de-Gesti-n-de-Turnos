<?php

namespace Tests\Feature;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Notification;
use App\Mail\CertificadoDigitalMail;
use App\Mail\TurnoConfirmadoMail;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserType;
use App\Services\AppointmentNotificationService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationStatusTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private Patient $doctor;
    private Patient $otherDoctor;
    private Patient $admin;
    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient, ['first_name' => 'Lucia', 'last_name' => 'Gomez']);
        $this->doctor = $this->createUser(UserTypeName::Doctor);
        $this->otherDoctor = $this->createUser(UserTypeName::Doctor);
        $this->admin = $this->createUser(null, [], RoleName::Administrator);
        $this->specialty = Specialty::create(['name' => 'Cardiología']);
    }

    private function createUser(?UserTypeName $type, array $attributes = [], RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create($attributes + [
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    private function createAppointment(Patient $doctor): Appointment
    {
        return Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $this->specialty->id,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'status' => 'reservado',
        ]);
    }

    private function createNotification(Appointment $appointment, array $attributes = []): Notification
    {
        return Notification::create($attributes + [
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo' => 'recordatorio',
            'titulo' => 'Titulo',
            'mensaje' => 'Mensaje',
            'estado_envio' => NotificationDeliveryStatus::Sent,
        ]);
    }

    public function test_recordatorio_enviado_queda_en_estado_enviada(): void
    {
        Mail::fake();

        $notification = app(AppointmentNotificationService::class)
            ->notifyReminder($this->createAppointment($this->doctor));

        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->fresh()->estado_envio);
        $this->assertNotNull($notification->fresh()->enviada_at);
        $this->assertNull($notification->fresh()->error_envio);
    }

    public function test_falla_de_envio_queda_fallida_con_error_y_no_lanza_excepcion(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $notification = app(AppointmentNotificationService::class)
            ->notifyReminder($this->createAppointment($this->doctor));

        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->fresh()->estado_envio);
        $this->assertSame('SMTP caído', $notification->fresh()->error_envio);
        $this->assertNull($notification->fresh()->enviada_at);
    }

    public function test_notificacion_interna_sin_mail_queda_enviada(): void
    {
        app(AppointmentNotificationService::class)->notifyCancellation($this->createAppointment($this->doctor), 'Motivo');

        $this->assertSame(0, Notification::where('estado_envio', '!=', NotificationDeliveryStatus::Sent->value)->count());
        $this->assertSame(2, Notification::count());
    }

    public function test_confirmacion_de_pago_registra_notificacion_enviada(): void
    {
        Mail::fake();

        $notification = app(AppointmentNotificationService::class)
            ->notifyPaymentConfirmed($this->createAppointment($this->doctor), new Payment());

        $this->assertSame('confirmacion', $notification->tipo);
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->fresh()->estado_envio);
        Mail::assertSent(TurnoConfirmadoMail::class);
    }

    public function test_falla_en_confirmacion_de_pago_no_lanza_excepcion(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Sin conexión'));

        $notification = app(AppointmentNotificationService::class)
            ->notifyPaymentConfirmed($this->createAppointment($this->doctor), new Payment());

        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->fresh()->estado_envio);
        $this->assertSame('Sin conexión', $notification->fresh()->error_envio);
    }

    public function test_certificado_registra_notificacion_enviada_y_fallida(): void
    {
        $appointment = $this->createAppointment($this->doctor);
        $service = app(AppointmentNotificationService::class);

        Mail::fake();
        $enviada = $service->notifyCertificate($appointment, 'certificados/a.pdf', 'http://localhost/ver');
        $this->assertSame(NotificationDeliveryStatus::Sent, $enviada->fresh()->estado_envio);
        Mail::assertSent(CertificadoDigitalMail::class);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Rechazado'));
        $fallida = $service->notifyCertificate($appointment, 'certificados/a.pdf', 'http://localhost/ver');
        $this->assertSame(NotificationDeliveryStatus::Failed, $fallida->fresh()->estado_envio);
    }

    public function test_comando_de_recordatorios_registra_estado_y_reintenta_solo_fallidos(): void
    {
        $this->createAppointment($this->doctor);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP caído'));
        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();
        $this->assertSame(1, Notification::where('estado_envio', NotificationDeliveryStatus::Failed->value)->count());

        Mail::swap(new \Illuminate\Mail\MailManager($this->app));
        Mail::fake();
        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();
        $this->assertSame(1, Notification::where('estado_envio', NotificationDeliveryStatus::Sent->value)->count());

        $this->artisan('turnos:enviar-recordatorios')->assertSuccessful();
        $this->assertSame(2, Notification::count());
    }

    public function test_listado_requiere_rol_medico_o_admin(): void
    {
        Sanctum::actingAs($this->patient);

        $this->getJson('/api/notifications')->assertStatus(403);
    }

    public function test_admin_ve_todo_y_medico_solo_sus_turnos(): void
    {
        $this->createNotification($this->createAppointment($this->doctor));
        $this->createNotification($this->createAppointment($this->otherDoctor));

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs($this->doctor);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filtros_por_estado_tipo_fechas_y_paciente(): void
    {
        $appointment = $this->createAppointment($this->doctor);
        $this->createNotification($appointment, ['estado_envio' => NotificationDeliveryStatus::Failed, 'error_envio' => 'Error']);
        $this->createNotification($appointment, ['tipo' => 'cancelacion']);
        $viejo = $this->createNotification($appointment, ['tipo' => 'certificado']);
        $viejo->forceFill(['created_at' => now()->subDays(10)])->save();

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/notifications?estado=fallida')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estado_envio', 'fallida');
        $this->getJson('/api/notifications?tipo=cancelacion')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/notifications?desde=' . now()->subDays(2)->toDateString())->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/notifications?hasta=' . now()->subDays(5)->toDateString())->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/notifications?paciente=Lucia')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/notifications?paciente=inexistente')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filtro_de_medico_respeta_su_alcance(): void
    {
        $this->createNotification($this->createAppointment($this->doctor));
        $this->createNotification($this->createAppointment($this->otherDoctor));

        Sanctum::actingAs($this->doctor);

        $this->getJson('/api/notifications?paciente=Lucia')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filtros_invalidos_devuelven_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/notifications?estado=otra')->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->getJson('/api/notifications?desde=2026-11-10&hasta=2026-11-01')->assertStatus(422)->assertJsonValidationErrors('hasta');
    }
}
