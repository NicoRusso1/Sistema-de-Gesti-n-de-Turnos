<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    // Alcances de visualización de la agenda (GT-54 / GT-55)
    public const SCOPE_ALL = 'all';
    public const SCOPE_ASSIGNED = 'assigned';
    public const SCOPE_OWN = 'own';

    // Duración de cada turno (bloque de la agenda del médico)
    public const DURATION_MINUTES = 15;

    // Estados que ocupan agenda y sala
    public const ACTIVE_STATUSES = ['reservado', 'abonado', 'scheduled', 'confirmed'];

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'specialty_id',
        'sala_id',
        'health_insurance_id',
        'affiliate_number',
        'monto_base',
        'monto_descuento',
        'monto_final',
        'scheduled_at',
        'status',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at'    => 'datetime',
            'monto_base'      => 'decimal:2',
            'monto_descuento' => 'decimal:2',
            'monto_final'     => 'decimal:2',
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

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class, 'sala_id');
    }

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'appointment_id');
    }

    // Mismo criterio que scopeVisibleTo, para un turno puntual: el médico solo
    // ve los turnos donde está asignado y el paciente solo los propios
    public function isVisibleTo(Patient $user): bool
    {
        return match ($user->appointmentScope()) {
            self::SCOPE_ALL => true,
            self::SCOPE_ASSIGNED => (int) $this->doctor_id === (int) $user->id,
            self::SCOPE_OWN => (int) $this->patient_id === (int) $user->id,
            default => false,
        };
    }

    // GT-55: filtra la consulta según el alcance del usuario logueado
    public function scopeVisibleTo(Builder $query, string $scope, Patient $user): Builder
    {
        return match ($scope) {
            self::SCOPE_OWN => $query->where('patient_id', $user->id),
            self::SCOPE_ASSIGNED => $query->where('doctor_id', $user->id),
            default => $query,
        };
    }
}