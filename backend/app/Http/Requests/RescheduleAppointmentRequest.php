<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        // Turno inexistente: que el controlador responda 404
        $appointment = Appointment::find($this->route('id'));
        if (!$appointment) {
            return true;
        }

        // Solo se reprograma un turno que el usuario puede ver: el médico los
        // que tiene asignados, el paciente los propios, la secretaria/admin todos
        if (!$appointment->isVisibleTo($user)) {
            return false;
        }

        // Admin, quien tenga 'edit_schedules' o el paciente titular
        return $user->hasAdminAccess()
            || $user->hasPermissionFlag('edit_schedules')
            || $user->appointmentScope() === Appointment::SCOPE_OWN;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date_format:Y-m-d H:i'],
            'sala_id'      => ['nullable', 'integer', 'exists:salas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_at.required'    => 'La nueva fecha y hora es obligatoria.',
            'scheduled_at.date_format' => 'El formato debe ser YYYY-MM-DD HH:mm.',
            'sala_id.exists'           => 'La sala seleccionada no existe.',
        ];
    }
}