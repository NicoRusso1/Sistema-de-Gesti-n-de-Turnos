<?php

namespace Tests\Feature;

use App\Enums\DocumentIssueStatus;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\PaymentMethodName;
use App\Enums\PaymentStatusName;
use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Mail\TurnoConfirmadoMail;
use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserType;
use App\Services\InvoiceService;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PaymentStatusSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GT-111: generación de la factura al aprobarse el pago, envío por email
 * y reintento cuando falla la emisión o el envío.
 */
class InvoiceRetryTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private Patient $doctor;
    private Patient $admin;
    private Appointment $appointment;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('documentos');
        $this->seed([RoleSeeder::class, UserTypeSeeder::class, PaymentMethodSeeder::class, PaymentStatusSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient, ['first_name' => 'Lucia', 'last_name' => 'Gomez']);
        $this->doctor = $this->createUser(UserTypeName::Doctor);
        $this->admin = $this->createUser(null, [], RoleName::Administrator);

        $specialty = Specialty::create(['name' => 'Cardiología']);

        $this->appointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => $specialty->id,
            'monto_base' => 10000,
            'monto_descuento' => 0,
            'monto_final' => 10000,
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
            'status' => 'reservado',
        ]);

        $this->payment = Payment::create([
            'appointment_id' => $this->appointment->id,
            'payment_method_id' => PaymentMethod::where('name', PaymentMethodName::Credit->value)->first()->id,
            'payment_status_id' => PaymentStatus::where('name', PaymentStatusName::Pending->value)->first()->id,
            'amount' => 10000,
        ]);
    }

    private function createUser(?UserTypeName $type, array $attributes = [], RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create($attributes + [
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

    private function failInvoiceGeneration(string $error = 'DomPDF sin memoria'): void
    {
        $this->mock(InvoiceService::class, fn ($mock) => $mock->shouldReceive('generate')->andThrow(new \RuntimeException($error)));
    }

    private function restoreInvoiceGeneration(): void
    {
        $this->app->forgetInstance(InvoiceService::class);
    }

    private function failMail(string $error = 'SMTP caído'): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException($error));
    }

    private function restoreMail(): void
    {
        Mail::swap(new MailManager($this->app));
        Mail::fake();
    }

    private function certificate(): ?Certificate
    {
        return Certificate::where('appointment_id', $this->appointment->id)->first();
    }

    // ── Generación y envío ──────────────────────────────────────────────

    public function test_pago_aprobado_emite_factura_en_disco_privado_y_la_envia(): void
    {
        Mail::fake();

        $this->approvePayment();

        $certificate = $this->certificate();
        $this->assertSame(DocumentIssueStatus::Issued, $certificate->estado_emision);
        $this->assertNull($certificate->error_emision);
        Storage::disk('documentos')->assertExists($certificate->invoice_url);
        Storage::disk('public')->assertMissing($certificate->invoice_url);
        Mail::assertSent(TurnoConfirmadoMail::class, fn ($mail) => count($mail->attachments()) === 2);
    }

    public function test_factura_incluye_los_datos_requeridos(): void
    {
        $this->approvePayment();
        $this->payment->refresh()->load('method');

        $html = view('invoices.invoice', [
            'appointment' => $this->appointment->fresh()->load(['patient', 'doctor', 'specialty']),
            'payment' => $this->payment,
            'issueDate' => now(),
        ])->render();

        $this->assertStringContainsString('Lucia Gomez', $html);
        $this->assertStringContainsString("Turno #{$this->appointment->id}", $html);
        $this->assertStringContainsString('10.000,00', $html);
        $this->assertStringContainsString($this->payment->method->name, $html);
        $this->assertStringContainsString(now()->format('d/m/Y'), $html);
        $this->assertStringContainsString('N° ' . str_pad($this->payment->id, 8, '0', STR_PAD_LEFT), $html);
    }

    // ── Fallas registradas ──────────────────────────────────────────────

    public function test_falla_de_emision_queda_registrada_y_no_envia_mail(): void
    {
        Mail::fake();
        $this->failInvoiceGeneration();

        $this->approvePayment();

        $this->assertSame('abonado', $this->appointment->fresh()->status);
        $this->assertSame(DocumentIssueStatus::Failed, $this->certificate()->estado_emision);
        $this->assertSame('DomPDF sin memoria', $this->certificate()->error_emision);

        $notification = Notification::where('tipo', 'confirmacion')->first();
        $this->assertSame(NotificationDeliveryStatus::Failed, $notification->estado_envio);
        $this->assertStringContainsString('No se pudo emitir la factura', $notification->error_envio);
        Mail::assertNothingSent();
    }

    // ── Reintento automático (cron) ─────────────────────────────────────

    public function test_cron_reintenta_la_emision_fallida_y_envia_la_factura(): void
    {
        Mail::fake();
        $this->failInvoiceGeneration();
        $this->approvePayment();
        $certificadoOriginal = $this->certificate()->certificate_url;

        $this->restoreInvoiceGeneration();
        $this->artisan('facturas:reintentar')->assertSuccessful();

        $certificate = $this->certificate();
        $this->assertSame(DocumentIssueStatus::Issued, $certificate->estado_emision);
        $this->assertNull($certificate->error_emision);
        // El certificado ya estaba emitido: solo se regenera lo que faltaba
        $this->assertSame($certificadoOriginal, $certificate->certificate_url);
        Storage::disk('documentos')->assertExists($certificate->invoice_url);
        Mail::assertSent(TurnoConfirmadoMail::class, 1);
        $this->assertSame(1, Notification::where('tipo', 'confirmacion')->where('estado_envio', 'enviada')->count());
    }

    public function test_cron_reintenta_el_envio_fallido_sin_reemitir_y_no_duplica(): void
    {
        $this->failMail();
        $this->approvePayment();
        $facturaOriginal = $this->certificate()->invoice_url;
        $this->assertSame(1, Notification::where('estado_envio', 'fallida')->count());

        $this->restoreMail();
        $this->artisan('facturas:reintentar')->assertSuccessful();
        $this->artisan('facturas:reintentar')->assertSuccessful();

        Mail::assertSent(TurnoConfirmadoMail::class, 1);
        $this->assertSame($facturaOriginal, $this->certificate()->invoice_url);
        $this->assertSame(1, Notification::where('tipo', 'confirmacion')->where('estado_envio', 'enviada')->count());
    }

    public function test_cron_deja_de_reintentar_al_superar_el_maximo(): void
    {
        config(['notifications.invoice_retry.max_attempts' => 2]);
        $this->failMail();
        $this->approvePayment();

        $this->artisan('facturas:reintentar')->assertSuccessful();
        $this->assertSame(2, Notification::where('estado_envio', 'fallida')->count());

        $this->artisan('facturas:reintentar')->assertSuccessful();
        $this->assertSame(2, Notification::where('estado_envio', 'fallida')->count());
    }

    public function test_cron_ignora_pagos_viejos(): void
    {
        $this->failMail();
        $this->approvePayment();
        $this->payment->forceFill(['payment_date' => now()->subDays(10)])->saveQuietly();

        $this->restoreMail();
        $this->artisan('facturas:reintentar')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_reintento_esta_programado(): void
    {
        $evento = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'facturas:reintentar'));

        $this->assertNotNull($evento);
        $this->assertSame('*/15 * * * *', $evento->expression);
    }

    // ── Reintento manual (endpoint) ─────────────────────────────────────

    public function test_admin_reintenta_manualmente_un_envio_fallido(): void
    {
        $this->failMail();
        $this->approvePayment();
        $this->restoreMail();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/appointments/{$this->appointment->id}/invoice/retry")
            ->assertOk()
            ->assertJson(['message' => 'Factura emitida y enviada al paciente', 'estado_emision' => 'emitida']);

        Mail::assertSent(TurnoConfirmadoMail::class, 1);

        $this->postJson("/api/appointments/{$this->appointment->id}/invoice/retry")
            ->assertOk()
            ->assertJson(['message' => 'La factura ya estaba emitida y enviada']);
        Mail::assertSent(TurnoConfirmadoMail::class, 1);
    }

    public function test_reintento_manual_que_vuelve_a_fallar_devuelve_el_error(): void
    {
        $this->failInvoiceGeneration('Disco lleno');
        Mail::fake();
        $this->approvePayment();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/appointments/{$this->appointment->id}/invoice/retry")
            ->assertStatus(502)
            ->assertJson(['error' => 'Disco lleno', 'estado_emision' => 'fallida']);
    }

    public function test_solo_admin_puede_reintentar(): void
    {
        $this->approvePayment();

        Sanctum::actingAs($this->patient);
        $this->postJson("/api/appointments/{$this->appointment->id}/invoice/retry")->assertStatus(403);

        Sanctum::actingAs($this->doctor);
        $this->postJson("/api/appointments/{$this->appointment->id}/invoice/retry")->assertStatus(403);
    }

    public function test_reintento_sin_pago_aprobado_devuelve_404(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/appointments/{$this->appointment->id}/invoice/retry")->assertStatus(404);
        $this->postJson('/api/appointments/99999/invoice/retry')->assertStatus(404);
    }

    // ── Almacenamiento (GT-110) ─────────────────────────────────────────

    public function test_paciente_descarga_la_factura_desde_el_disco_privado(): void
    {
        Mail::fake();
        $this->approvePayment();

        Sanctum::actingAs($this->patient);
        $this->get("/api/appointments/{$this->appointment->id}/invoice/download")
            ->assertOk()
            ->assertDownload("Factura_Turno_{$this->appointment->id}.pdf");
    }

    public function test_migrar_documentos_mueve_los_pdf_viejos_al_disco_privado(): void
    {
        Storage::disk('public')->put('facturas/vieja.pdf', 'pdf');
        Certificate::create([
            'appointment_id' => $this->appointment->id,
            'invoice_url' => 'facturas/vieja.pdf',
            'issue_date' => now(),
            'estado_emision' => DocumentIssueStatus::Issued,
        ]);

        // Antes de migrar se sigue pudiendo descargar
        Sanctum::actingAs($this->patient);
        $this->get("/api/appointments/{$this->appointment->id}/invoice/download")->assertOk();

        $this->artisan('documentos:migrar')->assertSuccessful();

        Storage::disk('documentos')->assertExists('facturas/vieja.pdf');
        Storage::disk('public')->assertMissing('facturas/vieja.pdf');
        $this->get("/api/appointments/{$this->appointment->id}/invoice/download")->assertOk();
    }
}
