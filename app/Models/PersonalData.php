<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalData extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patient_id',
        'national_id',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

}