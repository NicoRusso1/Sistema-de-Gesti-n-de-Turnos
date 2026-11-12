<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Sala;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GlobalMetricsService
{
    /**
     * Obtiene todas las métricas agregadas del centro médico para un período dado.
     *
     * @param Carbon $desde
     * @param Carbon $hasta
     * @return array
     */
    public function getMetrics(Carbon $desde, Carbon $hasta): array
    {
        return [
            'periodo' => [
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'dias'  => $desde->diffInDays($hasta) + 1,
            ],
            'kpis' => [
                'ingresos_totales'     => $this->calcularIngresosTotales($desde, $hasta),
                'total_turnos'         => $this->contarTurnosPeriodo($desde, $hasta),
                'total_salas_activas'  => Sala::where('status', 1)->count(),
                'total_usuarios_act'   => Patient::where('status', 1)->count(),
            ],
            'turnos_por_especialidad' => $this->turnosPorEspecialidad($desde, $hasta),
            'ocupacion_salas'         => $this->ocupacionSalas($desde, $hasta),
            'usuarios_desglose'       => $this->desgloseUsuarios(),
            'estados_turnos'          => $this->turnosPorEstado($desde, $hasta),
        ];
    }

    private function calcularIngresosTotales(Carbon $desde, Carbon $hasta): float
    {
        // Considera pagos aprobados en el período
        $ingresos = Payment::whereHas('status', fn ($q) => $q->where('name', 'approved'))
            ->where(function ($q) use ($desde, $hasta) {
                $q->whereBetween('payment_date', [$desde, $hasta])
                  ->orWhere(function ($sub) use ($desde, $hasta) {
                      $sub->whereNull('payment_date')
                          ->whereBetween('created_at', [$desde, $hasta]);
                  });
            })
            ->sum('amount');

        return round((float) $ingresos, 2);
    }

    private function contarTurnosPeriodo(Carbon $desde, Carbon $hasta): int
    {
        return Appointment::whereBetween('scheduled_at', [$desde, $hasta])->count();
    }

    private function turnosPorEspecialidad(Carbon $desde, Carbon $hasta): array
    {
        $turnos = Appointment::whereBetween('scheduled_at', [$desde, $hasta])
            ->select('specialty_id', DB::raw('count(*) as total'))
            ->groupBy('specialty_id')
            ->with('specialty')
            ->get();

        $totalGeneral = $turnos->sum('total');

        return $turnos->map(function ($t) use ($totalGeneral) {
            $nombre = $t->specialty ? $t->specialty->name : 'Sin especialidad';
            $porcentaje = $totalGeneral > 0 ? round(($t->total / $totalGeneral) * 100, 1) : 0;

            return [
                'especialidad' => $nombre,
                'total'        => (int) $t->total,
                'porcentaje'   => $porcentaje,
            ];
        })->sortByDesc('total')->values()->all();
    }

    private function ocupacionSalas(Carbon $desde, Carbon $hasta): array
    {
        $salas = Sala::all();
        $turnosConSala = Appointment::whereBetween('scheduled_at', [$desde, $hasta])
            ->whereNotNull('sala_id')
            ->select('sala_id', DB::raw('count(*) as turnos_asignados'))
            ->groupBy('sala_id')
            ->pluck('turnos_asignados', 'sala_id');

        return $salas->map(function ($sala) use ($turnosConSala) {
            $asignados = $turnosConSala[$sala->id] ?? 0;

            return [
                'sala_id'          => $sala->id,
                'numero'           => $sala->numero,
                'piso'             => $sala->piso,
                'estado'           => (int) $sala->status === 1 ? 'Activa' : 'Inactiva',
                'turnos_atendidos' => (int) $asignados,
            ];
        })->sortByDesc('turnos_atendidos')->values()->all();
    }

    private function desgloseUsuarios(): array
    {
        $pacientes = Patient::whereHas('userType', fn ($q) => $q->where('name', UserTypeName::Patient->value))
            ->where('status', 1)->count();

        $medicos = Patient::whereHas('userType', fn ($q) => $q->where('name', UserTypeName::Doctor->value))
            ->where('status', 1)->count();

        $administradores = Patient::whereHas('role', fn ($q) => $q->whereIn('name', [RoleName::Administrator->value, RoleName::SuperAdmin->value]))
            ->where('status', 1)->count();

        return [
            'pacientes_activos'      => $pacientes,
            'medicos_activos'        => $medicos,
            'administradores_activos'=> $administradores,
            'total_activos'          => $pacientes + $medicos + $administradores,
        ];
    }

    private function turnosPorEstado(Carbon $desde, Carbon $hasta): array
    {
        return Appointment::whereBetween('scheduled_at', [$desde, $hasta])
            ->select('status', DB::raw('count(*) as cantidad'))
            ->groupBy('status')
            ->pluck('cantidad', 'status')
            ->all();
    }
}