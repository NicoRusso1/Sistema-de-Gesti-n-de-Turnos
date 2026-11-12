<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'turno_id',
        'monto_original',
        'descuento_aplicado',
        'monto_final',
        'estado',
        'metodo_pago',
        'transaccion_id',
        'provider_preference_id',
        'fecha_pago',
        'error_mensaje',
    ];

    protected function casts(): array
    {
        return [
            'monto_original'     => 'decimal:2',
            'descuento_aplicado' => 'decimal:2',
            'monto_final'        => 'decimal:2',
            'fecha_pago'         => 'datetime',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'turno_id');
    }

    public function isAprobado(): bool
    {
        return $this->estado === 'aprobado';
    }

    public function isPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }
}