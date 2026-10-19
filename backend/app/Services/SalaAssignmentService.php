<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Sala;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * GT-41 y GT-42: asignación de salas a turnos.
 *
 * Un turno ocupa su sala durante el bloque [scheduled_at, scheduled_at + DURATION_MINUTES).
 * Dos turnos activos no pueden compartir sala si sus bloques se superponen.
 */
class SalaAssignmentService
{
    /**
     * Busca una sala disponible para la especialidad en ese horario (GT-41).
     * Devuelve null si no hay ninguna libre: el turno queda sin sala y se puede asignar a mano.
     */
    public function findAvailableSala(?int $specialtyId, Carbon $start, ?int $ignoreAppointmentId = null): ?Sala
    {
        if (!$specialtyId) {
            return null;
        }

        // lockForUpdate: llamado dentro de una transacción, bloquea estas salas
        // hasta guardar el turno (dos reservas a la vez no eligen la misma)
        return Sala::where('estado', 'disponible')
            ->where('especialidad_id', $specialtyId)
            ->orderBy('piso')
            ->orderBy('numero')
            ->lockForUpdate()
            ->get()
            ->first(fn (Sala $sala) => !$this->hasConflict($sala->id, $start, $ignoreAppointmentId));
    }

    /**
     * Salas que se pueden asignar a mano a un turno: disponibles y sin cruce de horario.
     * Primero las de la especialidad del turno.
     */
    public function assignableSalas(Appointment $appointment): Collection
    {
        return Sala::with('specialty')
            ->where('estado', 'disponible')
            ->orderBy('piso')
            ->orderBy('numero')
            ->get()
            ->reject(fn (Sala $sala) => $this->hasConflict($sala->id, $appointment->scheduled_at, $appointment->id))
            ->sortBy(fn (Sala $sala) => $sala->especialidad_id === $appointment->specialty_id ? 0 : 1)
            ->values();
    }

    /**
     * GT-42: true si la sala ya tiene otro turno activo que se superpone con el bloque que empieza en $start.
     */
    public function hasConflict(int $salaId, Carbon $start, ?int $ignoreAppointmentId = null): bool
    {
        $duration = Appointment::DURATION_MINUTES;

        // Superposición: inicio_existente < fin_nuevo  Y  fin_existente > inicio_nuevo
        return Appointment::where('sala_id', $salaId)
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->where('scheduled_at', '<', $start->copy()->addMinutes($duration))
            ->where('scheduled_at', '>', $start->copy()->subMinutes($duration))
            ->exists();
    }
}
