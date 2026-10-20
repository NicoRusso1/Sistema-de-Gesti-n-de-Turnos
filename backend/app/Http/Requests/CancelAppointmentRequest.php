<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class CancelAppointmentRequest extends FormRequest
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

        // Solo se cancela un turno que el usuario puede ver: el médico los que
        // tiene asignados, el paciente los propios, la secretaria/admin todos
        if (!$appointment->isVisibleTo($user)) {
            return false;
        }

        // Admin, quien tenga 'cancel_appointments' o el paciente titular
        return $user->hasAdminAccess()
            || $user->hasPermissionFlag('cancel_appointments')
            || $user->appointmentScope() === Appointment::SCOPE_OWN;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.max' => 'El motivo de cancelación no puede exceder los 255 caracteres.',
        ];
    }
}