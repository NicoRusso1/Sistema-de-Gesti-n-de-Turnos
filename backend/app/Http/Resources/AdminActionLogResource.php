<?php

namespace App\Http\Resources;

use App\Enums\AdminActionName;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\AdminActionLog */
class AdminActionLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $name = $this->action?->name;

        return [
            'id' => $this->id,
            'date' => $this->date?->toIso8601String(),
            'action' => [
                'name' => $name,
                'label' => AdminActionName::tryFrom((string) $name)?->label() ?? $name,
            ],
            'actor' => $this->person($this->superAdmin),
            'affected' => $this->person($this->affectedAdmin),
        ];
    }

    private function person(?Patient $patient): ?array
    {
        if (! $patient) {
            return null;
        }

        return [
            'id' => $patient->id,
            'name' => trim($patient->first_name . ' ' . $patient->last_name),
        ];
    }
}
