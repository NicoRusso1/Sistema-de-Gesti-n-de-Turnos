<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActionLog extends Model
{
    public $timestamps = false;
    protected $table = 'admin_action_log';

    protected $fillable = ['superadmin_id', 'affected_admin_id', 'action_id', 'date'];

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'superadmin_id');
    }

    public function affectedAdmin(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'affected_admin_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AdminAction::class, 'action_id');
    }
}