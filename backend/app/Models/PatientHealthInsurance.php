<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientHealthInsurance extends Model
{
    public $timestamps = false;

    protected $table = 'patient_health_insurance';

    protected $fillable = [
        'patient_id',
        'health_insurance_id',
        'member_number',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class);
    }
}