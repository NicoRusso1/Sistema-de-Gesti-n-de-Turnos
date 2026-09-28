<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'view_own_appointments',
        'view_all_appointments',
        'view_assigned_patients',
        'cancel_appointments',
        'edit_schedules',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    protected function casts(): array
    {
        return [
            'view_own_appointments' => 'boolean',
            'view_all_appointments' => 'boolean',
            'view_assigned_patients' => 'boolean',
            'cancel_appointments' => 'boolean',
            'edit_schedules' => 'boolean',
        ];
    }
}