<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    public $timestamps = false;

    protected $fillable = ['number', 'floor', 'specialty_id', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
