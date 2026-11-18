<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\PatientHealthInsurance;
use App\Models\Sala;
use App\Models\Specialty;
use App\Services\AppointmentPricingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoTurnosSeeder extends Seeder
{
    private const MEDICOS = [
        'juan' => 'medico@hospital.test',
        'carolina' => 'carolina.benitez@hospital.test',
        'lucia' => 'lucia.marchetti@hospital.test',
        'martin' => 'martin.acosta@hospital.test',
    ];

    private const PACIENTES = [
        'maria' => 'paciente@hospital.test',
        'sofia' => 'sofia.ramirez@hospital.test',
        'diego' => 'diego.sosa@hospital.test',
        'camila' => 'camila.torres@hospital.test',
        'julian' => 'julian.medina@hospital.test',
        'valentina' => 'valentina.romero@hospital.test',
        'facundo' => 'facundo.alvarez@hospital.test',
        'agustina' => 'agustina.castro@hospital.test',
        'tomas' => 'tomas.herrera@hospital.test',
    ];

    private const MONTOS = [
        'Cardiología' => 28000,
        'Clínica Médica' => 18000,
        'Pediatria' => 16500,
        'Ginecología' => 22000,
    ];

    private const MOTIVOS_CANCELACION = [
        'Imprevisto laboral',
        'El paciente no pudo asistir',
        'Cambio de planes por viaje',
        'Cuadro resuelto, ya no necesita la consulta',
        'Reprogramación a pedido del paciente',
    ];

    private array $ocupacionMedico = [];

    private array $ocupacionSala = [];

    private array $horarios = [];

    public function run(): void
    {
        $medicos = Patient::whereIn('email', self::MEDICOS)->get()->keyBy('email');
        $pacientes = Patient::whereIn('email', self::PACIENTES)->get()->keyBy('email');

        $idsPacientesNuevos = $pacientes->except(self::PACIENTES['maria'])->pluck('id')->all();

        if (Appointment::whereIn('patient_id', $idsPacientesNuevos)->exists()) {
            $this->command?->warn('Ya hay turnos de demostración cargados: se conservan tal cual.');

            return;
        }

        $especialidades = Specialty::pluck('id', 'name');
        $coberturas = PatientHealthInsurance::all()->keyBy('patient_id');
        $salas = Sala::where('estado', 'disponible')
            ->orderBy('piso')
            ->orderBy('numero')
            ->get()
            ->groupBy('especialidad_id');

        $this->cargarOcupacionExistente();

        DB::transaction(function () use ($medicos, $pacientes, $especialidades, $coberturas, $salas) {
            foreach ($this->plan() as $indice => $turno) {
                [$dias, $hora, $aliasMedico, $aliasPaciente, $especialidad, $estado] = $turno;
                $opciones = $turno[6] ?? [];

                $medico = $medicos[self::MEDICOS[$aliasMedico]];
                $paciente = $pacientes[self::PACIENTES[$aliasPaciente]];

                $momento = $dias === 0 ? ($estado === 'completado' ? 'pasado' : 'futuro') : 'dia';
                $programado = $this->resolverHorario($medico->id, $dias, $hora, $momento);

                if (!$programado) {
                    throw new RuntimeException("No hay un horario libre para el turno {$indice} de demostración.");
                }

                if ($estado === 'completado' && $programado->isFuture()) {
                    $estado = 'abonado';
                }

                $cancelado = $estado === 'cancelado';
                $especialidadId = $especialidades[$especialidad];
                $cobertura = $coberturas->get($paciente->id);

                $precio = app(AppointmentPricingService::class)->calculate(
                    (float) self::MONTOS[$especialidad],
                    $cobertura?->health_insurance_id
                );

                $sala = null;
                if (!$cancelado && empty($opciones['sin_sala'])) {
                    $sala = $this->elegirSala($salas->get($especialidadId, collect()), $programado, $indice);
                }

                $creado = $this->fechaDeCreacion($programado, $indice);

                $turnoNuevo = new Appointment([
                    'patient_id' => $paciente->id,
                    'doctor_id' => $medico->id,
                    'specialty_id' => $especialidadId,
                    'sala_id' => $sala?->id,
                    'health_insurance_id' => $cobertura?->health_insurance_id,
                    'affiliate_number' => $cobertura?->member_number,
                    'monto_base' => $precio['monto_base'],
                    'monto_descuento' => $precio['monto_descuento'],
                    'monto_final' => $precio['monto_final'],
                    'scheduled_at' => $programado,
                    'status' => $estado,
                    'cancellation_reason' => $cancelado
                        ? self::MOTIVOS_CANCELACION[$indice % count(self::MOTIVOS_CANCELACION)]
                        : null,
                ]);

                $turnoNuevo->forceFill([
                    'created_at' => $creado,
                    'updated_at' => $this->fechaDeActualizacion($estado, $programado, $creado, $indice),
                ])->save();

                if (!$cancelado) {
                    $this->ocupacionMedico[$medico->id . '|' . $programado->timestamp] = true;

                    if ($sala) {
                        $this->ocupacionSala[$sala->id][] = $programado->timestamp;
                    }
                }
            }
        });
    }

    private function plan(): array
    {
        return [
            [-13, '09:30', 'juan', 'maria', 'Clínica Médica', 'completado'],
            [-9, '10:15', 'martin', 'maria', 'Clínica Médica', 'completado'],
            [-6, '16:00', 'juan', 'maria', 'Cardiología', 'completado'],
            [-4, '11:00', 'juan', 'maria', 'Clínica Médica', 'cancelado'],
            [-2, '09:45', 'juan', 'maria', 'Cardiología', 'completado'],
            [0, '15:30', 'juan', 'maria', 'Clínica Médica', 'abonado'],
            [1, '10:30', 'juan', 'maria', 'Cardiología', 'abonado'],
            [3, '15:30', 'lucia', 'maria', 'Ginecología', 'abonado'],
            [5, '11:15', 'martin', 'maria', 'Clínica Médica', 'reservado'],
            [8, '09:15', 'juan', 'maria', 'Clínica Médica', 'reservado'],
            [11, '17:00', 'juan', 'maria', 'Cardiología', 'cancelado'],

            [0, '09:00', 'juan', 'sofia', 'Cardiología', 'completado'],
            [0, '10:00', 'juan', 'diego', 'Clínica Médica', 'completado'],
            [0, '11:30', 'juan', 'valentina', 'Clínica Médica', 'completado'],
            [0, '16:15', 'juan', 'camila', 'Cardiología', 'abonado'],
            [0, '17:00', 'juan', 'facundo', 'Clínica Médica', 'reservado'],
            [1, '09:00', 'juan', 'julian', 'Cardiología', 'abonado'],
            [1, '09:30', 'juan', 'agustina', 'Pediatria', 'reservado'],
            [1, '11:00', 'juan', 'tomas', 'Clínica Médica', 'abonado'],
            [2, '16:00', 'juan', 'sofia', 'Cardiología', 'reservado'],
            [-3, '10:00', 'juan', 'julian', 'Cardiología', 'completado'],
            [-7, '15:00', 'juan', 'agustina', 'Clínica Médica', 'completado'],
            [-12, '16:30', 'juan', 'valentina', 'Cardiología', 'cancelado'],

            [-11, '08:30', 'carolina', 'valentina', 'Pediatria', 'completado'],
            [-8, '09:15', 'carolina', 'facundo', 'Pediatria', 'completado'],
            [-5, '10:00', 'carolina', 'agustina', 'Pediatria', 'completado'],
            [-1, '08:45', 'carolina', 'tomas', 'Pediatria', 'cancelado'],
            [2, '09:00', 'carolina', 'julian', 'Pediatria', 'abonado'],
            [6, '10:30', 'carolina', 'facundo', 'Pediatria', 'reservado'],

            [-14, '14:30', 'lucia', 'camila', 'Ginecología', 'completado'],
            [-7, '10:00', 'lucia', 'valentina', 'Ginecología', 'completado'],
            [-3, '15:15', 'lucia', 'sofia', 'Ginecología', 'completado'],
            [4, '10:30', 'lucia', 'valentina', 'Ginecología', 'abonado'],
            [8, '16:00', 'lucia', 'camila', 'Ginecología', 'reservado'],
            [13, '09:30', 'lucia', 'agustina', 'Ginecología', 'cancelado'],

            [-12, '10:30', 'martin', 'diego', 'Clínica Médica', 'completado'],
            [-6, '11:15', 'martin', 'julian', 'Clínica Médica', 'completado'],
            [-2, '16:30', 'martin', 'facundo', 'Clínica Médica', 'completado'],
            [-1, '12:00', 'martin', 'tomas', 'Clínica Médica', 'cancelado'],
            [3, '12:30', 'martin', 'diego', 'Clínica Médica', 'reservado', ['sin_sala' => true]],
        ];
    }

    private function cargarOcupacionExistente(): void
    {
        $existentes = Appointment::where('status', '!=', 'cancelado')
            ->whereBetween('scheduled_at', [today()->subDays(15), today()->addDays(16)])
            ->get();

        foreach ($existentes as $turno) {
            $this->ocupacionMedico[$turno->doctor_id . '|' . $turno->scheduled_at->timestamp] = true;

            if ($turno->sala_id) {
                $this->ocupacionSala[$turno->sala_id][] = $turno->scheduled_at->timestamp;
            }
        }
    }

    private function resolverHorario(int $medicoId, int $dias, string $hora, string $momento): ?Carbon
    {
        if ($dias === 0) {
            $ahora = now();
            $franjas = $this->franjasDelDia($medicoId, today());

            $franjas = $momento === 'pasado'
                ? $franjas->filter(fn (Carbon $franja) => $franja->lte($ahora->copy()->subMinutes(15)))
                : $franjas->filter(fn (Carbon $franja) => $franja->gt($ahora->copy()->addMinutes(15)));

            if ($elegida = $this->elegirFranja($medicoId, $franjas, $hora)) {
                return $elegida;
            }

            $dias = $momento === 'pasado' ? -1 : 1;
        }

        foreach ($this->diasCandidatos($dias) as $candidato) {
            $franjas = $this->franjasDelDia($medicoId, today()->addDays($candidato));

            if ($elegida = $this->elegirFranja($medicoId, $franjas, $hora)) {
                return $elegida;
            }
        }

        return null;
    }

    private function diasCandidatos(int $dias): array
    {
        $signo = $dias < 0 ? -1 : 1;
        $candidatos = [];

        for ($paso = 0; $paso <= 14; $paso++) {
            foreach ([$dias + $paso, $dias - $paso] as $candidato) {
                if ($candidato * $signo >= 1 && abs($candidato) <= 14 && !in_array($candidato, $candidatos, true)) {
                    $candidatos[] = $candidato;
                }
            }
        }

        return $candidatos;
    }

    private function franjasDelDia(int $medicoId, Carbon $dia): Collection
    {
        $dia = $dia->copy()->startOfDay();

        return $this->horariosDelMedico($medicoId)
            ->where('day_of_week', $dia->dayOfWeekIso)
            ->sortBy('start_time')
            ->flatMap(function (DoctorSchedule $horario) use ($dia) {
                $franjas = [];
                $cursor = $dia->copy()->setTimeFromTimeString($horario->start_time);
                $fin = $dia->copy()->setTimeFromTimeString($horario->end_time);

                while ($cursor->copy()->addMinutes(Appointment::DURATION_MINUTES)->lte($fin)) {
                    $franjas[] = $cursor->copy();
                    $cursor->addMinutes(Appointment::DURATION_MINUTES);
                }

                return $franjas;
            })
            ->values();
    }

    private function horariosDelMedico(int $medicoId): Collection
    {
        return $this->horarios[$medicoId] ??= DoctorSchedule::where('doctor_id', $medicoId)->get();
    }

    private function elegirFranja(int $medicoId, Collection $franjas, string $hora): ?Carbon
    {
        $libres = $franjas->reject(
            fn (Carbon $franja) => isset($this->ocupacionMedico[$medicoId . '|' . $franja->timestamp])
        );

        if ($libres->isEmpty()) {
            return null;
        }

        $elegida = $libres->first(fn (Carbon $franja) => $franja->format('H:i') >= $hora) ?? $libres->last();

        return $elegida->copy();
    }

    private function elegirSala(Collection $salas, Carbon $inicio, int $rotacion): ?Sala
    {
        $cantidad = $salas->count();

        for ($paso = 0; $paso < $cantidad; $paso++) {
            $sala = $salas->values()[($rotacion + $paso) % $cantidad];

            if (!$this->salaOcupada($sala->id, $inicio)) {
                return $sala;
            }
        }

        return null;
    }

    private function salaOcupada(int $salaId, Carbon $inicio): bool
    {
        $bloque = Appointment::DURATION_MINUTES * 60;

        foreach ($this->ocupacionSala[$salaId] ?? [] as $momento) {
            if (abs($momento - $inicio->timestamp) < $bloque) {
                return true;
            }
        }

        return false;
    }

    private function fechaDeCreacion(Carbon $programado, int $indice): Carbon
    {
        $creado = $programado->copy()
            ->subDays(2 + $indice % 4)
            ->setTime(9 + $indice % 8, ($indice * 7) % 60);

        return $creado->gt(now()) ? now()->subHours(1 + $indice % 20) : $creado;
    }

    private function fechaDeActualizacion(string $estado, Carbon $programado, Carbon $creado, int $indice): Carbon
    {
        $actualizado = match ($estado) {
            'completado' => $programado->copy()->addMinutes(25),
            'cancelado' => $creado->copy()->addHours(5 + $indice % 30),
            default => $creado->copy(),
        };

        return $actualizado->gt(now()) ? now() : $actualizado;
    }
}
