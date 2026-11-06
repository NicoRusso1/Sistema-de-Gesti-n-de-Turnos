<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Mail\CertificadoDigitalMail;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Role;
use App\Models\Sala;
use App\Models\Specialty;
use App\Models\UserType;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IssueCertificateTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;
    private Patient $doctor;
    private Patient $otherDoctor;
    private Patient $admin;
    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Mail::fake();
        $this->seed([RoleSeeder::class, UserTypeSeeder::class]);

        $this->patient = $this->createUser(UserTypeName::Patient);
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
    }

    private function createUser(?UserTypeName $type, RoleName $role = RoleName::OperationalUser): Patient
    {
        return Patient::factory()->create([
            'role_id' => Role::where('name', $role->value)->first()->id,
            'user_type_id' => $type ? UserType::where('name', $type->value)->first()->id : null,
        ]);
    }

    public function test_medico_asignado_emite_el_certificado(): void
    {
        Sanctum::actingAs($this->doctor);

        $this->postJson("/api/appointments/{$this->appointment->id}/certificate", ['observaciones' => 'Reposo'])
            ->assertOk();

        $this->assertNotNull($this->appointment->fresh()->certificado_url);
        Mail::assertSent(CertificadoDigitalMail::class);
    }

    public function test_otro_medico_recibe_403_y_no_se_genera_certificado(): void
    {
        Sanctum::actingAs($this->otherDoctor);

        $this->postJson("/api/appointments/{$this->appointment->id}/certificate")
            ->assertStatus(403)
            ->assertJson(['message' => 'Solo el médico asignado al turno puede emitir el certificado']);

        $this->assertEmpty($this->appointment->fresh()->certificado_url);
        Mail::assertNothingSent();
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_administrador_emite_el_certificado(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/appointments/{$this->appointment->id}/certificate")->assertOk();

        $this->assertNotNull($this->appointment->fresh()->certificado_url);
    }

    public function test_paciente_recibe_403(): void
    {
        Sanctum::actingAs($this->patient);

        $this->postJson("/api/appointments/{$this->appointment->id}/certificate")->assertStatus(403);

        $this->assertEmpty($this->appointment->fresh()->certificado_url);
        Mail::assertNothingSent();
    }

    public function test_turno_inexistente_devuelve_404(): void
    {
        Sanctum::actingAs($this->doctor);

        $this->postJson('/api/appointments/999999/certificate')->assertStatus(404);
    }
}
