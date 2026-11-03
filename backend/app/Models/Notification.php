<?php

namespace App\Models;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'tipo',
        'titulo',
        'mensaje',
        'leido',
        'estado_envio',
        'error_envio',
        'enviada_at',
    ];

    protected function casts(): array
    {
        return [
            'leido' => 'boolean',
            'estado_envio' => NotificationDeliveryStatus::class,
            'enviada_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }
}