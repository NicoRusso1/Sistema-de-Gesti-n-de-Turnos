<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Appointment extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'scheduled_at',
        'status',
    ];
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
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
}