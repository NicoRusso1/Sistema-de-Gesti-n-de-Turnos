<?php

namespace App\Models;

use App\Enums\DocumentIssueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    protected $fillable = [
        'appointment_id',
        'doctor_id',
        'patient_id',
        'content',
        'issue_date',
        'file_url',
        'estado_emision',
        'error_emision',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'datetime',
            'estado_emision' => DocumentIssueStatus::class,
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'doctor_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
