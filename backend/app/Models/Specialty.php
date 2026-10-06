<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
    ];

    // Médicos que tienen asignada esta especialidad
    public function doctors(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    // Turnos reservados para esta especialidad
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
