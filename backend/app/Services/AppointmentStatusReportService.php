<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GT-67: reporte de turnos agrupados y desglosados por estado.
 */
class AppointmentStatusReportService
{
    /**
     * Estados del reporte, en el orden del ciclo de vida del turno.
     */
    public const STATES = [
        'reservado' => [
            'etiqueta'    => 'Reservado',
            'descripcion' => 'El paciente reservó el turno y todavía no registró el pago.',
        ],
        'abonado' => [
            'etiqueta'    => 'Abonado',
            'descripcion' => 'El turno tiene el pago aprobado y está confirmado.',
        ],
        'en_espera' => [
            'etiqueta'    => 'En espera',
            'descripcion' => 'El paciente se presentó en el centro y aguarda ser atendido.',
        ],
        'completado' => [
            'etiqueta'    => 'Completado',
            'descripcion' => 'El médico finalizó la consulta.',
        ],
        'cancelado' => [
            'etiqueta'    => 'Cancelado',
            'descripcion' => 'El turno fue cancelado por el paciente, el centro o por vencimiento del plazo de pago.',
        ],
    ];

    /**
     * Valores heredados que todavía pueden existir en la tabla y se suman a su estado equivalente.
     */
    private const ALIASES = [
        'scheduled' => 'reservado',
        'confirmed' => 'abonado',
        'cancelled' => 'cancelado',
    ];

    public function generate(?Carbon $desde, ?Carbon $hasta, ?int $specialtyId, ?int $doctorId): array
    {
        $conteos = Appointment::query()
            ->when($desde, fn ($q) => $q->where('scheduled_at', '>=', $desde))
            ->when($hasta, fn ($q) => $q->where('scheduled_at', '<=', $hasta))
            ->when($specialtyId, fn ($q) => $q->where('specialty_id', $specialtyId))
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->select('status', DB::raw('count(*) as cantidad'))
            ->groupBy('status')
            ->pluck('cantidad', 'status');

        $porEstado = array_fill_keys(array_keys(self::STATES), 0);
        foreach ($conteos as $status => $cantidad) {
            $clave = self::ALIASES[$status] ?? $status;
            if (array_key_exists($clave, $porEstado)) {
                $porEstado[$clave] += (int) $cantidad;
            }
        }

        $total = array_sum($porEstado);

        $estados = [];
        foreach (self::STATES as $clave => $meta) {
            $estados[] = [
                'estado'      => $clave,
                'etiqueta'    => $meta['etiqueta'],
                'descripcion' => $meta['descripcion'],
                'cantidad'    => $porEstado[$clave],
                'porcentaje'  => $total > 0 ? round($porEstado[$clave] * 100 / $total, 1) : 0,
            ];
        }

        return [
            'estados'      => $estados,
            'total'        => $total,
            'filtros'      => [
                'fecha_desde'  => $desde?->toDateString(),
                'fecha_hasta'  => $hasta?->toDateString(),
                'specialty_id' => $specialtyId,
                'doctor_id'    => $doctorId,
            ],
            'generado_en'  => now()->toIso8601String(),
        ];
    }
}
