<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
    ];
}