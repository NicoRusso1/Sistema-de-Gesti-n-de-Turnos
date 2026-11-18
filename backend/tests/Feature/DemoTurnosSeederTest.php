<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DemoTurnosSeederTest extends TestCase
{
    use RefreshDatabase;

    public static function momentosDeEjecucion(): array
    {
        return [
            'miércoles a la tarde' => ['2026-11-18 14:00'],
            'sábado a la mañana' => ['2026-11-21 10:00'],
            'lunes muy temprano' => ['2026-11-23 06:30'],
            'viernes a la noche' => ['2026-11-20 22:30'],
        ];
    }

    #[DataProvider('momentosDeEjecucion')]
    public function test_los_turnos_cumplen_las_reglas_del_modelo(string $momento): void
    {
        $this->travelTo(Carbon::parse($momento));
        $this->seed(DemoSeeder::class);

        $turnos = Appointment::with(['sala', 'doctor.specialties', 'healthInsurance'])->get();

        $this->assertGreaterThanOrEqual(35, $turnos->count());
        $this->assertLessThanOrEqual(45, $turnos->count());

        foreach (['reservado', 'abonado', 'completado', 'cancelado'] as $estado) {
            $this->assertTrue($turnos->contains('status', $estado), "Falta un turno {$estado}");
        }

        $desde = today()->subDays(14)->startOfDay();
        $hasta = today()->addDays(14)->endOfDay();

        foreach ($turnos as $turno) {
            $this->assertTrue($turno->scheduled_at->betweenIncluded($desde, $hasta));
            $this->assertSame(0, $turno->scheduled_at->minute % Appointment::DURATION_MINUTES);
            $this->assertTrue($turno->doctor->specialties->contains('id', $turno->specialty_id));
            $this->assertEqualsWithDelta((float) $turno->monto_base - (float) $turno->monto_descuento, (float) $turno->monto_final, 0.001);
            $this->assertGreaterThan(0, (float) $turno->monto_final);
            $this->assertTrue($turno->created_at->lte(now()));

            if ($turno->health_insurance_id) {
                $porcentaje = (float) $turno->healthInsurance->discount_percentage;
                $this->assertEqualsWithDelta((float) $turno->monto_base * $porcentaje / 100, (float) $turno->monto_descuento, 0.01);
                $this->assertNotEmpty($turno->affiliate_number);
            } else {
                $this->assertEquals(0, $turno->monto_descuento);
            }

            if ($turno->status === 'completado') {
                $this->assertTrue($turno->scheduled_at->isPast());
            }

            if (in_array($turno->status, ['reservado', 'abonado'], true)) {
                $this->assertTrue($turno->scheduled_at->isFuture());
            }

            if ($turno->status === 'cancelado') {
                $this->assertNull($turno->sala_id);
                $this->assertNotEmpty($turno->cancellation_reason);
            }

            if ($turno->sala_id) {
                $this->assertSame('disponible', $turno->sala->estado);
                $this->assertNotSame('105', $turno->sala->numero);
                $this->assertSame($turno->specialty_id, $turno->sala->especialidad_id);
            }
        }

        $this->assertTrue($turnos->contains(fn ($t) => $t->status !== 'cancelado' && $t->sala_id === null));
        $this->assertTrue($turnos->contains(fn ($t) => $t->health_insurance_id !== null));
        $this->assertTrue($turnos->contains(fn ($t) => $t->health_insurance_id === null));
    }

    #[DataProvider('momentosDeEjecucion')]
    public function test_los_turnos_caen_en_franjas_validas_sin_cruces(string $momento): void
    {
        $this->travelTo(Carbon::parse($momento));
        $this->seed(DemoSeeder::class);

        $activos = Appointment::where('status', '!=', 'cancelado')->get();

        foreach ($activos as $turno) {
            $horarios = DoctorSchedule::where('doctor_id', $turno->doctor_id)
                ->where('day_of_week', $turno->scheduled_at->dayOfWeekIso)
                ->get();

            $dentro = $horarios->contains(function ($horario) use ($turno) {
                $inicio = $turno->scheduled_at->copy()->setTimeFromTimeString($horario->start_time);
                $fin = $turno->scheduled_at->copy()->setTimeFromTimeString($horario->end_time);

                return $turno->scheduled_at->gte($inicio)
                    && $turno->scheduled_at->copy()->addMinutes(Appointment::DURATION_MINUTES)->lte($fin);
            });

            $this->assertTrue($dentro, "Turno #{$turno->id} fuera de la franja del médico");
        }

        foreach (['doctor_id', 'sala_id'] as $recurso) {
            foreach ($activos->whereNotNull($recurso)->groupBy($recurso) as $grupo) {
                $horas = $grupo->map(fn ($t) => $t->scheduled_at->timestamp)->sort()->values();

                for ($i = 1; $i < $horas->count(); $i++) {
                    $this->assertGreaterThanOrEqual(
                        Appointment::DURATION_MINUTES * 60,
                        $horas[$i] - $horas[$i - 1],
                        "Superposición en {$recurso}"
                    );
                }
            }
        }
    }

    public function test_maria_tiene_historial_completo_y_el_doctor_perez_agenda_de_hoy_y_los_proximos_dias(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 14:00'));
        $this->seed(DemoSeeder::class);

        $maria = Patient::where('email', 'paciente@hospital.test')->first();
        $turnosMaria = Appointment::where('patient_id', $maria->id)->get();

        foreach (['reservado', 'abonado', 'completado', 'cancelado'] as $estado) {
            $this->assertTrue($turnosMaria->contains('status', $estado));
        }
        $this->assertGreaterThanOrEqual(3, $turnosMaria->filter(fn ($t) => $t->scheduled_at->isPast())->count());
        $this->assertGreaterThanOrEqual(3, $turnosMaria->filter(fn ($t) => $t->scheduled_at->isFuture())->count());
        $this->assertNull($maria->healthInsuranceRecord);
        $this->assertTrue($turnosMaria->every(fn ($t) => $t->health_insurance_id === null));

        $juan = Patient::where('email', 'medico@hospital.test')->first();
        $turnosJuan = Appointment::where('doctor_id', $juan->id)->get();
        $hoy = $turnosJuan->filter(fn ($t) => $t->scheduled_at->isToday());
        $manana = $turnosJuan->filter(fn ($t) => $t->scheduled_at->isTomorrow());

        $this->assertGreaterThanOrEqual(3, $hoy->filter(fn ($t) => $t->scheduled_at->isPast())->count());
        $this->assertGreaterThanOrEqual(2, $hoy->filter(fn ($t) => $t->scheduled_at->isFuture())->count());
        $this->assertGreaterThanOrEqual(2, $manana->count());
        $this->assertGreaterThanOrEqual(2, $turnosJuan->filter(fn ($t) => $t->scheduled_at->gt(today()->addDays(2)->startOfDay()))->count());
    }

    public function test_correrlo_dos_veces_no_duplica_turnos(): void
    {
        $this->seed(DemoSeeder::class);
        $cantidad = Appointment::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($cantidad, Appointment::count());
    }
}
