<?php

namespace Tests\Feature;

use App\Enums\AdminActionName;
use App\Models\AdminActionLog;
use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Support\Documents;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederCompletoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documentos');
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_cada_turno_abonado_o_completado_tiene_un_unico_pago_aprobado_por_su_monto_final(): void
    {
        $this->seed(DemoSeeder::class);

        $turnos = Appointment::with('payments.status')->whereIn('status', ['abonado', 'completado'])->get();
        $this->assertNotEmpty($turnos);

        foreach ($turnos as $turno) {
            $this->assertCount(1, $turno->payments, "El turno #{$turno->id} debe tener un solo pago");
            $pago = $turno->payments->first();
            $this->assertSame('approved', $pago->status->name);
            $this->assertEquals($turno->monto_final, $pago->amount);
            $this->assertNotNull($pago->payment_date);
            $this->assertNotNull($pago->payment_method_id);
            $this->assertTrue($pago->payment_date->lte(now()));
        }

        $this->assertSame(3, Payment::whereNotNull('payment_method_id')->distinct()->count('payment_method_id'));
    }

    public function test_los_turnos_reservados_tienen_pagos_pendientes_o_rechazados_y_los_cancelados_ninguno(): void
    {
        $this->seed(DemoSeeder::class);

        $reservados = Appointment::with('payments.status')->where('status', 'reservado')->get();

        foreach ($reservados as $turno) {
            $this->assertCount(1, $turno->payments);
            $this->assertContains($turno->payments->first()->status->name, ['pending', 'rejected']);
        }

        $estados = Payment::with('status')->get()->pluck('status.name')->unique();
        $this->assertEqualsCanonicalizing(['approved', 'pending', 'rejected'], $estados->all());
        $this->assertSame(0, Payment::whereIn('appointment_id', Appointment::where('status', 'cancelado')->pluck('id'))->count());

        foreach (Payment::with('status')->get() as $pago) {
            if ($pago->status->name === 'pending') {
                $this->assertNull($pago->provider_payment_id);
                $this->assertNull($pago->payment_date);
            } else {
                $this->assertNotEmpty($pago->provider_payment_id);
            }
        }
    }

    public function test_las_notificaciones_muestran_distintos_estados_y_tipos(): void
    {
        $this->seed(DemoSeeder::class);

        $estados = Notification::pluck('estado_envio')->map(fn ($e) => $e->value)->unique()->all();
        $this->assertEqualsCanonicalizing(['pendiente', 'enviada', 'fallida'], $estados);

        foreach (['confirmacion', 'recordatorio', 'cancelacion'] as $tipo) {
            $this->assertTrue(Notification::where('tipo', $tipo)->exists(), "Faltan notificaciones de {$tipo}");
        }

        $this->assertSame(0, Notification::where('estado_envio', 'fallida')->whereNull('error_envio')->count());
        $this->assertSame(0, Notification::where('estado_envio', 'enviada')->whereNull('enviada_at')->count());
        $this->assertSame(Appointment::where('status', 'cancelado')->count() * 2, Notification::where('tipo', 'cancelacion')->count());
    }

    public function test_certificados_y_recetas_tienen_pdf_real(): void
    {
        $this->seed(DemoSeeder::class);

        $aprobados = Appointment::whereIn('status', ['abonado', 'completado'])->count();
        $this->assertSame($aprobados, Certificate::count());

        foreach (Certificate::all() as $certificado) {
            $this->assertSame('emitida', $certificado->estado_emision->value);
            $this->assertTrue(Documents::exists($certificado->certificate_url));
            $this->assertTrue(Documents::exists($certificado->invoice_url));
        }

        $this->assertNotEmpty(Prescription::all());

        foreach (Prescription::all() as $receta) {
            $this->assertSame('emitida', $receta->estado_emision->value);
            $this->assertTrue(Documents::exists($receta->file_url));
            $this->assertSame('completado', $receta->appointment->status);
        }

        $maria = Patient::where('email', 'paciente@hospital.test')->first();
        $completadosDeMaria = Appointment::where('patient_id', $maria->id)->where('status', 'completado')->pluck('id');
        $this->assertSame($completadosDeMaria->count(), Prescription::whereIn('appointment_id', $completadosDeMaria)->count());
        $this->assertSame($completadosDeMaria->count(), Appointment::whereIn('id', $completadosDeMaria)->whereNotNull('certificado_token')->count());
    }

    public function test_el_historial_de_auditoria_es_coherente_con_el_estado_de_los_usuarios(): void
    {
        $this->seed(DemoSeeder::class);

        $total = AdminActionLog::count();
        $this->assertGreaterThanOrEqual(6, $total);
        $this->assertLessThanOrEqual(8, $total);

        $superAdmin = Patient::where('email', 'superadmin@hospital.test')->first();
        $this->assertSame($total, AdminActionLog::where('superadmin_id', $superAdmin->id)->count());

        $gabriela = Patient::where('email', 'gabriela.dominguez@hospital.test')->first();
        $marcela = Patient::where('email', 'marcela.ibarra@hospital.test')->first();
        $admin = Patient::where('email', 'admin@hospital.test')->first();

        $this->assertSame('Administrator', $gabriela->role->name);
        $this->assertSame('OperationalUser', $marcela->role->name);
        $this->assertTrue($admin->is_owner);
        $this->assertSame(1, $gabriela->status);

        $accionesDeMarcela = AdminActionLog::with('action')->where('affected_admin_id', $marcela->id)->orderBy('date')->get();
        $this->assertSame(
            [AdminActionName::GrantAdmin->value, AdminActionName::RevokeAdmin->value],
            $accionesDeMarcela->pluck('action.name')->all()
        );

        $this->assertTrue(AdminActionLog::whereHas('action', fn ($q) => $q->where('name', 'grant_owner'))->where('affected_admin_id', $admin->id)->exists());
        $this->assertTrue(AdminActionLog::whereHas('action', fn ($q) => $q->where('name', 'deactivate'))->exists());
        $this->assertTrue(AdminActionLog::whereHas('action', fn ($q) => $q->where('name', 'activate'))->exists());
        $this->assertSame(0, AdminActionLog::where('date', '>', now())->count());
    }

    public function test_correrlo_dos_veces_no_cambia_ninguna_cantidad_ni_falla(): void
    {
        $this->seed(DemoSeeder::class);
        $antes = $this->cantidades();
        $archivos = count(Storage::disk('documentos')->allFiles());

        $this->seed(DemoSeeder::class);

        $this->assertSame($antes, $this->cantidades());
        $this->assertCount($archivos, Storage::disk('documentos')->allFiles());
    }

    public function test_no_envia_correos_ni_llama_a_servicios_externos(): void
    {
        $this->seed(DemoSeeder::class);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame(26, Notification::where('tipo', 'confirmacion')->count());
    }

    public function test_muestra_la_tabla_de_cuentas_de_demostracion(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])
            ->expectsOutputToContain('paciente@hospital.test')
            ->expectsOutputToContain('Gabriela Domínguez')
            ->assertExitCode(0);
    }

    private function cantidades(): array
    {
        return [
            'patients' => Patient::count(),
            'appointments' => Appointment::count(),
            'payments' => Payment::count(),
            'notificaciones' => Notification::count(),
            'certificados' => Certificate::count(),
            'prescriptions' => Prescription::count(),
            'auditoria' => AdminActionLog::count(),
        ];
    }
}
