<?php

namespace App\Models;

use App\Enums\UserTypeName;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/** @use HasFactory<PatientFactory> */
class Patient extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password_hash',
        'phone',
        'user_type_id',
        'role_id',
        'status',
        'is_owner',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
            'is_owner' => 'boolean',
            'registration_date' => 'datetime',
            'status' => 'integer',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function hasAdminAccess(): bool
    {
        return $this->isAdministrator() || $this->isSuperAdmin();
    }

    public function isOwner(): bool
    {
        return $this->isAdministrator() && $this->is_owner;
    }


    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function userType(): BelongsTo
    {
        return $this->belongsTo(UserType::class);
    }

    public function personalData(): HasOne
    {
        return $this->hasOne(PersonalData::class);
    }

    public function permissions(): HasOne
    {
        return $this->hasOne(UserPermission::class);
    }
        public function healthInsuranceRecord(): HasOne
    {
        return $this->hasOne(PatientHealthInsurance::class);
    }

    public function healthInsurance(): HasOneThrough
    {
        return $this->hasOneThrough(
            HealthInsurance::class,
            PatientHealthInsurance::class,
            'patient_id',
            'id',
            'id',
            'health_insurance_id'
        );
    }

    // Especialidad del médico
    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(
            Specialty::class,
            'medico_especialidad',
            'medico_id',
            'especialidad_id'
        );
    }
    public function appointmentsAsPatient(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }
    public function appointmentsAsDoctor(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function scopeAssignedTo(Builder $query, Patient $doctor): Builder
    {
        return $query->whereHas(
            'appointmentsAsPatient',
            fn($q) => $q->where('doctor_id', $doctor->id)
        );
    }

    public function scopeDoctors(Builder $query): Builder
    {
        return $query->whereHas('userType', fn($q) => $q->where('name', UserTypeName::Doctor->value));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }


    public function isSuperAdmin(): bool
    {
        return $this->role->name === \App\Enums\RoleName::SuperAdmin->value;
    }

    public function isAdministrator(): bool
    {
        return $this->role->name === \App\Enums\RoleName::Administrator->value;
    }

    public function isDoctor(): bool
    {
        return $this->userType?->name === \App\Enums\UserTypeName::Doctor->value;
    }

    public function isSecretary(): bool
    {
        return $this->userType?->name === \App\Enums\UserTypeName::Secretary->value;
    }

    public function isPatientType(): bool
    {
        return $this->userType?->name === \App\Enums\UserTypeName::Patient->value;
    }

    /**
     * Alcance de la agenda de turnos (GT-54) según tipo de usuario y permisos:
     * - Administrador / SuperAdmin, o flag view_all_appointments → "all"
     * - Médico con view_assigned_patients                         → "assigned"
     * - Paciente con view_own_appointments                        → "own"
     * null si no tiene ningún permiso para ver turnos.
     */
    public function appointmentScope(): ?string
    {
        if ($this->hasAdminAccess() || $this->hasPermissionFlag('view_all_appointments')) {
            return Appointment::SCOPE_ALL;
        }

        if ($this->isDoctor() && $this->hasPermissionFlag('view_assigned_patients')) {
            return Appointment::SCOPE_ASSIGNED;
        }

        if ($this->isPatientType() && $this->hasPermissionFlag('view_own_appointments')) {
            return Appointment::SCOPE_OWN;
        }

        return null;
    }

    public function hasPermissionFlag(string $flag): bool
    {
        // Si el usuario todavía no tiene fila en user_permissions (ej: paciente recién registrado)
        // se usan los permisos por defecto de su tipo de usuario
        if (!$this->permissions) {
            return (bool) (UserPermission::defaultsFor($this->userType?->name)[$flag] ?? false);
        }

        return (bool) ($this->permissions->{$flag} ?? false);
    }
}
