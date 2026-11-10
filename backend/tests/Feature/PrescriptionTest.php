<?php

namespace Tests\Feature;

use App\Enums\DocumentIssueStatus;
use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GT-219: finalizar la consulta y emitir la receta digital.
 */
class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT = "Amoxicilina 500 mg\n1 comprimido cada 8 hs durante 7 días.";

    private Patient $patient;
    private Patient $otherPatient;
    private Patient $doctor;
    private Patient $otherDoctor;
    private Patient $secretary;
    private Patient $admin;
    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('documentos');
        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient, ['first_name' => 'Lucia', 'last_name' => 'Gomez']);
        $this->otherPatient = $this->createUser(UserTypeName::Patient);
        $this->doctor = $this->createUser(UserTypeName::Doctor, ['first_name' => 'Ana', 'last_name' => 'Perez']);
        $this->otherDoctor = $this->createUser(UserTypeName::Doctor);
        $this->secretary = $this->createUser(UserTypeName::Secretary);
        $this->admin = $this->createUser(null, [], RoleName::Administrator);

        $this->appointment = Appointment::create([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'specialty_id' => Specialty::create(['name' => 'Clínica Médica'])->id,
            'monto_base' => 10000,
            'monto_descuento' => 0,
            'monto_final' => 10000,
            'scheduled_at' => now()->subHour(),
            'status' => 'abonado',
        ]);
    }

    private function createUser(?UserTypeName $type, array $attributes = [], RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create($attributes + [
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    private function complete(): void
    {
        $this->appointment->update(['status' => Appointment::STATUS_COMPLETED]);
    }

    private function issue(Patient $as, array $body = ['content' => self::CONTENT])
    {
        Sanctum::actingAs($as);

        return $this->postJson("/api/appointments/{$this->appointment->id}/prescriptions", $body);
    }

    private function failPdf(string $error = 'DomPDF sin memoria'): void
    {
        $this->instance('dompdf.wrapper', \Mockery::mock(\Barryvdh\DomPDF\PDF::class, function ($mock) use ($error) {
            $mock->shouldReceive('loadView')->andThrow(new \RuntimeException($error));
        }));
    }

    private function restorePdf(): void
    {
        $this->app->forgetInstance('dompdf.wrapper');
    }

    // ── Finalizar la consulta ───────────────────────────────────────────

    public function test_medico_asignado_finaliza_la_consulta(): void
    {
        Sanctum::actingAs($this->doctor);

        $this->patchJson("/api/appointments/{$this->appointment->id}/complete")
            ->assertOk()
            ->assertJson(['status' => 'completado']);

        $this->assertSame('completado', $this->appointment->fresh()->status);
    }

    public function test_solo_el_medico_asignado_puede_finalizar(): void
    {
        foreach ([$this->otherDoctor, $this->patient, $this->secretary, $this->admin] as $user) {
            Sanctum::actingAs($user);
            $this->patchJson("/api/appointments/{$this->appointment->id}/complete")->assertStatus(403);
        }

        $this->assertSame('abonado', $this->appointment->fresh()->status);
    }

    public function test_no_se_finaliza_un_turno_cancelado_futuro_o_ya_finalizado(): void
    {
        Sanctum::actingAs($this->doctor);

        $this->appointment->update(['status' => 'cancelado']);
        $this->patchJson("/api/appointments/{$this->appointment->id}/complete")->assertStatus(422);

        $this->appointment->update(['status' => 'abonado', 'scheduled_at' => now()->addDays(2)]);
        $this->patchJson("/api/appointments/{$this->appointment->id}/complete")->assertStatus(422);

        $this->appointment->update(['status' => 'completado', 'scheduled_at' => now()->subHour()]);
        $this->patchJson("/api/appointments/{$this->appointment->id}/complete")->assertStatus(422);
    }

    public function test_finalizar_turno_inexistente_devuelve_404(): void
    {
        Sanctum::actingAs($this->doctor);

        $this->patchJson('/api/appointments/99999/complete')->assertStatus(404);
    }

    public function test_turno_finalizado_no_se_cancela_ni_reprograma(): void
    {
        $this->complete();
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/appointments/{$this->appointment->id}/cancel", ['reason' => 'x'])->assertStatus(400);
        $this->assertSame('completado', $this->appointment->fresh()->status);
    }

    // ── Emitir receta ───────────────────────────────────────────────────

    public function test_medico_asignado_emite_la_receta_con_el_turno_finalizado(): void
    {
        $this->complete();

        $this->issue($this->doctor)
            ->assertStatus(201)
            ->assertJsonPath('prescription.estado_emision', 'emitida');

        $prescription = Prescription::first();
        $this->assertSame($this->appointment->id, $prescription->appointment_id);
        $this->assertSame($this->doctor->id, $prescription->doctor_id);
        $this->assertSame($this->patient->id, $prescription->patient_id);
        $this->assertSame(self::CONTENT, $prescription->content);
        $this->assertNotNull($prescription->issue_date);
        $this->assertNotNull($prescription->file_url);
        Storage::disk('documentos')->assertExists($prescription->file_url);
        Storage::disk('public')->assertMissing($prescription->file_url);
    }

    public function test_no_se_emite_receta_si_el_turno_no_esta_finalizado(): void
    {
        $this->issue($this->doctor)->assertStatus(422);

        $this->assertSame(0, Prescription::count());
    }

    public function test_el_contenido_es_obligatorio(): void
    {
        $this->complete();

        $this->issue($this->doctor, ['content' => ''])->assertStatus(422)->assertJsonValidationErrors('content');
        $this->issue($this->doctor, [])->assertStatus(422)->assertJsonValidationErrors('content');

        $this->assertSame(0, Prescription::count());
    }

    public function test_otro_medico_no_puede_emitir_recetas_de_turnos_ajenos(): void
    {
        $this->complete();

        $this->issue($this->otherDoctor)->assertStatus(403);
        $this->assertSame(0, Prescription::count());
    }

    public function test_solo_usuarios_tipo_medico_acceden_a_la_funcion(): void
    {
        $this->complete();

        foreach ([$this->patient, $this->secretary, $this->admin] as $user) {
            $this->issue($user)->assertStatus(403);
        }

        $this->assertSame(0, Prescription::count());
    }

    public function test_pdf_incluye_contenido_paciente_medico_y_fecha(): void
    {
        $this->complete();
        $this->issue($this->doctor)->assertStatus(201);
        $prescription = Prescription::first()->load(['patient', 'doctor', 'appointment']);

        $html = view('prescriptions.prescription', [
            'prescription' => $prescription,
            'appointment' => $prescription->appointment,
            'issueDate' => $prescription->issue_date,
        ])->render();

        $this->assertStringContainsString('Amoxicilina 500 mg', $html);
        $this->assertStringContainsString('Lucia Gomez', $html);
        $this->assertStringContainsString('Dr/a. Ana Perez', $html);
        $this->assertStringContainsString($prescription->issue_date->format('d/m/Y'), $html);
        $this->assertStringContainsString("Turno N°:</strong> #{$this->appointment->id}", $html);
    }

    // ── Falla y reintento ───────────────────────────────────────────────

    public function test_si_falla_la_generacion_informa_el_error_y_guarda_el_contenido(): void
    {
        $this->complete();
        $this->failPdf();

        $this->issue($this->doctor)
            ->assertStatus(502)
            ->assertJsonPath('error', 'DomPDF sin memoria')
            ->assertJsonPath('prescription.estado_emision', 'fallida');

        $prescription = Prescription::first();
        $this->assertSame(DocumentIssueStatus::Failed, $prescription->estado_emision);
        $this->assertSame(self::CONTENT, $prescription->content);
        $this->assertNull($prescription->file_url);
    }

    public function test_reintento_genera_la_receta_con_el_contenido_guardado(): void
    {
        $this->complete();
        $this->failPdf();
        $this->issue($this->doctor)->assertStatus(502);
        $prescription = Prescription::first();

        $this->restorePdf();
        $this->postJson("/api/prescriptions/{$prescription->id}/retry")
            ->assertOk()
            ->assertJsonPath('prescription.estado_emision', 'emitida');

        $prescription->refresh();
        $this->assertSame(self::CONTENT, $prescription->content);
        $this->assertNull($prescription->error_emision);
        Storage::disk('documentos')->assertExists($prescription->file_url);
        $this->assertSame(1, Prescription::count());

        $this->postJson("/api/prescriptions/{$prescription->id}/retry")
            ->assertOk()
            ->assertJson(['message' => 'La receta ya estaba emitida']);
    }

    public function test_reintento_que_vuelve_a_fallar_devuelve_502(): void
    {
        $this->complete();
        $this->failPdf('Disco lleno');
        $this->issue($this->doctor);

        $this->postJson('/api/prescriptions/' . Prescription::first()->id . '/retry')
            ->assertStatus(502)
            ->assertJsonPath('error', 'Disco lleno');
    }

    public function test_solo_el_medico_emisor_puede_reintentar(): void
    {
        $this->complete();
        $this->failPdf();
        $this->issue($this->doctor);
        $this->restorePdf();
        $id = Prescription::first()->id;

        foreach ([$this->otherDoctor, $this->patient, $this->admin] as $user) {
            Sanctum::actingAs($user);
            $this->postJson("/api/prescriptions/{$id}/retry")->assertStatus(403);
        }

        $this->postJson('/api/prescriptions/99999/retry')->assertStatus(404);
    }

    // ── Ver y descargar ─────────────────────────────────────────────────

    public function test_paciente_medico_y_admin_ven_y_descargan_la_receta(): void
    {
        $this->complete();
        $this->issue($this->doctor)->assertStatus(201);
        $id = Prescription::first()->id;

        foreach ([$this->patient, $this->doctor, $this->admin] as $user) {
            Sanctum::actingAs($user);

            $view = $this->get("/api/prescriptions/{$id}/view")->assertOk();
            $this->assertStringContainsString('application/pdf', $view->headers->get('Content-Type'));
            $this->assertStringStartsWith('inline', $view->headers->get('Content-Disposition'));

            $this->get("/api/prescriptions/{$id}/download")
                ->assertOk()
                ->assertDownload("Receta_{$id}_Turno_{$this->appointment->id}.pdf");
        }
    }

    public function test_usuarios_ajenos_no_ven_la_receta(): void
    {
        $this->complete();
        $this->issue($this->doctor)->assertStatus(201);
        $id = Prescription::first()->id;

        foreach ([$this->otherPatient, $this->otherDoctor] as $user) {
            Sanctum::actingAs($user);
            $this->get("/api/prescriptions/{$id}/view")->assertStatus(403);
            $this->get("/api/prescriptions/{$id}/download")->assertStatus(403);
            $this->getJson("/api/appointments/{$this->appointment->id}/prescriptions")->assertStatus(403);
        }
    }

    public function test_receta_fallida_no_tiene_pdf_para_descargar(): void
    {
        $this->complete();
        $this->failPdf();
        $this->issue($this->doctor);

        Sanctum::actingAs($this->patient);
        $this->get('/api/prescriptions/' . Prescription::first()->id . '/download')->assertStatus(404);
    }

    public function test_listado_de_recetas_del_turno(): void
    {
        $this->complete();
        $this->issue($this->doctor)->assertStatus(201);
        $this->issue($this->doctor, ['content' => 'Reposo 48 hs'])->assertStatus(201);

        Sanctum::actingAs($this->patient);
        $this->getJson("/api/appointments/{$this->appointment->id}/prescriptions")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.content', 'Reposo 48 hs');
    }
}
