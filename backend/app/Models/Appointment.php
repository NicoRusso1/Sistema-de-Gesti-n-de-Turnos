<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'room_id',
        'date',
        'start_time',
        'end_time',
        'duration',
        'state_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'creation_date' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'doctor_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(AppointmentState::class, 'state_id');
    }

    /**
     * Sección 3 del documento: cada tipo de usuario ve un subconjunto
     * distinto de turnos.
     * - Super Admin / Administrator: ven todo (panel de control, Sección 4.6).
     * - Usuario Operativo con view_all_appointments (Secretaria): ve todo.
     * - Médico con view_own_appointments: solo los turnos donde él es
     *   el médico asignado.
     * - Paciente: solo sus propios turnos.
     */
    public function scopeVisibleFor(Builder $query, Patient $actor): Builder
    {
        if ($actor->isSuperAdmin() || $actor->isAdministrator()) {
            return $query;
        }

        if ($actor->hasPermissionFlag('view_all_appointments')) {
            return $query;
        }

        if ($actor->isDoctor() && $actor->hasPermissionFlag('view_own_appointments')) {
            return $query->where('doctor_id', $actor->id);
        }

        if ($actor->isPatientType()) {
            return $query->where('patient_id', $actor->id);
        }

        // Sin permisos reconocidos: no ve ningún turno.
        return $query->whereRaw('1 = 0');
    }
}
