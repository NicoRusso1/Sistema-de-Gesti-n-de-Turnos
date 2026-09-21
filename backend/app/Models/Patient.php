<?php

namespace App\Models;

use App\Enums\UserTypeName;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** @use HasFactory<PatientFactory> */
class Patient extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
    return $this->hasOne(UserPermission::class, 'user_id');
}

    public function scopeDoctors(Builder $query): Builder
    {
        return $query->whereHas('userType', fn ($q) => $q->where('name', UserTypeName::Doctor->value));
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

    public function hasPermissionFlag(string $flag): bool
    {
        return (bool) ($this->permissions?->{$flag} ?? false);
    }
}
