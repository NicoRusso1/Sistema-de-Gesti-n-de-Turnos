<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'specialty_id',
        'health_insurance_id',
        'affiliate_number',
        'monto_base',
        'monto_descuento',
        'monto_final',
        'scheduled_at',
        'status',
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

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class, 'health_insurance_id');
    }

        public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}