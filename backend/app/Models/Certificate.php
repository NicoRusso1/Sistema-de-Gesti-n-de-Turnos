<?php

namespace App\Models;

use App\Enums\DocumentIssueStatus;
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
        'estado_emision',
        'error_emision',
        'ultimo_intento_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'datetime',
            'estado_emision' => DocumentIssueStatus::class,
            'ultimo_intento_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
