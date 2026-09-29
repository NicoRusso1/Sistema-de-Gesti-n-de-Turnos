<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthInsurance extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'discount_percentage',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_percentage' => 'decimal:2',
            'status' => 'integer',
        ];
    }
}