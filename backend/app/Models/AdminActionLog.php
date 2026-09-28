<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\AdminActionName;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminActionLog extends Model
{
    public $timestamps = false;
    protected $table = 'admin_action_log';

    protected $fillable = ['superadmin_id', 'affected_admin_id', 'action_id', 'date'];

    public static function record(Patient $actor, Patient $affected, AdminActionName $action): self
    {
        return self::create([
            'superadmin_id' => $actor->id,
            'affected_admin_id' => $affected->id,
            'action_id' => AdminAction::where('name', $action->value)->firstOrFail()->id,
            'date' => now(),
        ]);
    }


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