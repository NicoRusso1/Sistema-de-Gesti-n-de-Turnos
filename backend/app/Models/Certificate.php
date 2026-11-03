<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $table = 'certificados';

    protected $fillable = [
        'appointment_id',
        'invoice_url',
        'certificate_url',
        'issue_date',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
