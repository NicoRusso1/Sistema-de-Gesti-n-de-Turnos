<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Sala extends Model
{
    protected $table = 'salas';

    protected $fillable = [
        'numero',
        'piso',
        'especialidad_id',
        'estado',
    ];

    protected $casts = [
        'piso' => 'integer',
        'especialidad_id' => 'integer',
    ];

    /**
     * Relación con la especialidad asignada
     */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class, 'especialidad_id');
    }

    /**
     * Relación con turnos (si existe la tabla en el sistema)
     */
    public function turnos()
    {
        // Si tienes o creas el modelo Appointment/Turno:
        if (class_exists(\App\Models\Turno::class)) {
            return $this->hasMany(\App\Models\Turno::class, 'sala_id');
        }
        if (class_exists(\App\Models\Appointment::class)) {
            return $this->hasMany(\App\Models\Appointment::class, 'sala_id');
        }
        return null;
    }
}