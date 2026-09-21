<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'view_own_appointments',
        'view_all_appointments',
        'view_assigned_patients',
        'cancel_appointments',
        'edit_schedules',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'user_id');
    }
}