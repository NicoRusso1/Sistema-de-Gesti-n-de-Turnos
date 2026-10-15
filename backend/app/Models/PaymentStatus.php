<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentStatus extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];
}
