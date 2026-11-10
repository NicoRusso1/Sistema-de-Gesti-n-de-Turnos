<?php

namespace Tests\Feature;

use App\Enums\PaymentMethodName;
use App\Enums\PaymentStatusName;
use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\Role;
use App\Models\Sala;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PaymentStatusSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Enums\NotificationDeliveryStatus;
use App\Mail\TurnoConfirmadoMail;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentVouchersTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private Patient $otherPatient;
    private Patient $doctor;
    private Patient $otherDoctor;
    private Patient $admin;
    private Appointment $appointment;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([RoleSeeder::class, UserTypeSeeder::class, PaymentMethodSeeder::class, PaymentStatusSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient);
        $this->otherPatient = $this->createUser(UserTypeName::Patient);
        $this->doctor = $this->createUser(UserTypeName::Doctor);
        $this->otherDoctor = $this->createUser(UserTypeName::Doctor);
        $this->admin = $this->createUser(null, RoleName::Administrator);

        $specialty = Specialty::create(['name' => 'Cardiología']);
        $sala = Sala::create(['numero' => 12, 'piso' => 3, 'especialidad_id' => $specialty->id, 'estado' => 'disponible']);

        $this->appointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => $specialty->id,
            'sala_id' => $sala->id,
            'monto_base' => 10000,
            'monto_descuento' => 0,
            'monto_final' => 10000,
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'status' => 'reservado',
        ]);

        $this->payment = Payment::create([
            'appointment_id' => $this->appointment->id,
            'payment_method_id' => PaymentMethod::where('name', PaymentMethodName::Credit->value)->first()->id,
            'payment_status_id' => PaymentStatus::where('name', PaymentStatusName::Pending->value)->first()->id,
            'amount' => 10000,
        ]);
    }

    private function createUser(?UserTypeName $type, RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create([
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    private function approvePayment(): void
    {
        $this->payment->update([
            'payment_status_id' => PaymentStatus::where('name', PaymentStatusName::Approved->value)->first()->id,
            'payment_date' => now(),
        ]);
    }

    public function test_pago_pendiente_no_genera_comprobantes(): void
    {
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_pago_aprobado_genera_factura_y_certificado(): void
    {
        $this->approvePayment();

        $this->assertSame('abonado', $this->appointment->fresh()->status);

        $certificate = Certificate::where('appointment_id', $this->appointment->id)->first();
        $this->assertNotNull($certificate);
        $this->assertNotNull($certificate->invoice_url);
        $this->assertNotNull($certificate->certificate_url);
        $this->assertNotNull($certificate->issue_date);
        Storage::disk('public')->assertExists($certificate->invoice_url);
        Storage::disk('public')->assertExists($certificate->certificate_url);
    }

    public function test_pago_aprobado_envia_email_con_factura_y_certificado_adjuntos(): void
    {
        Mail::fake();

        $this->approvePayment();

        Mail::assertSent(TurnoConfirmadoMail::class, function (TurnoConfirmadoMail $mail) {
            $nombres = collect($mail->attachments())->map(fn ($a) => $a->as)->all();

            return $mail->hasTo($this->patient->email)
                && in_array("Factura_Turno_{$this->appointment->id}.pdf", $nombres)
                && in_array("Certificado_Turno_{$this->appointment->id}.pdf", $nombres);
        });

        $notification = Notification::where('appointment_id', $this->appointment->id)->where('tipo', 'confirmacion')->first();
        $this->assertNotNull($notification);
        $this->assertSame(NotificationDeliveryStatus::Sent, $notification->estado_envio);
    }

    public function test_falla_del_email_post_pago_queda_fallida_y_no_corta_el_pago(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->approvePayment();

        $this->assertSame('abonado', $this->appointment->fresh()->status);
        $notification = Notification::where('tipo', 'confirmacion')->first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->estado_envio);
        $this->assertSame('SMTP caído', $notification->error_envio);
    }

    public function test_pago_pendiente_no_envia_email(): void
    {
        Mail::fake();

        $this->payment->update(['amount' => 9000]);

        Mail::assertNothingSent();
        $this->assertSame(0, Notification::where('tipo', 'confirmacion')->count());
    }

    public function test_paciente_titular_descarga_ambos_comprobantes(): void
    {
        $this->approvePayment();
        Sanctum::actingAs($this->patient);

        $this->get("/api/appointments/{$this->appointment->id}/invoice/download")
            ->assertOk()
            ->assertDownload("Factura_Turno_{$this->appointment->id}.pdf");

        $this->get("/api/appointments/{$this->appointment->id}/certificate/download")
            ->assertOk()
            ->assertDownload("Certificado_Turno_{$this->appointment->id}.pdf");
    }

    public function test_medico_asignado_y_administrador_pueden_descargar(): void
    {
        $this->approvePayment();

        foreach ([$this->doctor, $this->admin] as $user) {
            Sanctum::actingAs($user);

            $this->get("/api/appointments/{$this->appointment->id}/invoice/download")->assertOk();
            $this->get("/api/appointments/{$this->appointment->id}/certificate/download")->assertOk();
        }
    }

    public function test_usuarios_ajenos_reciben_403(): void
    {
        $this->approvePayment();

        foreach ([$this->otherPatient, $this->otherDoctor] as $user) {
            Sanctum::actingAs($user);

            $this->getJson("/api/appointments/{$this->appointment->id}/invoice/download")->assertStatus(403);
            $this->getJson("/api/appointments/{$this->appointment->id}/certificate/download")->assertStatus(403);
        }
    }

    public function test_sin_token_recibe_401(): void
    {
        $this->getJson("/api/appointments/{$this->appointment->id}/invoice/download")->assertStatus(401);
        $this->getJson("/api/appointments/{$this->appointment->id}/certificate/download")->assertStatus(401);
    }

    public function test_turno_sin_comprobantes_o_inexistente_devuelve_404(): void
    {
        Sanctum::actingAs($this->patient);

        $this->getJson("/api/appointments/{$this->appointment->id}/invoice/download")
            ->assertStatus(404)
            ->assertJsonPath('message', 'El turno no cuenta con una factura emitida');

        $this->getJson("/api/appointments/{$this->appointment->id}/certificate/download")
            ->assertStatus(404);

        $this->getJson('/api/appointments/99999/invoice/download')->assertStatus(404);
    }
}
