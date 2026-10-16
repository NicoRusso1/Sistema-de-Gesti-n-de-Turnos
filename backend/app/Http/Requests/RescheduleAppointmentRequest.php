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

        // Administradores y SuperAdmin tienen acceso completo
        if ($user->hasAdminAccess()) {
            return true;
        }

        // Operarios con permiso 'edit_schedules' o 'modificar_horarios'
        if ($user->hasPermissionFlag('edit_schedules') || $user->hasPermissionFlag('modificar_horarios')) {
            return true;
        }

        // El paciente titular puede reprogramar su propio turno si no está cancelado
        $appointment = Appointment::find($this->route('id'));
        if ($appointment && $appointment->patient_id === $user->id) {
            return true;
        }

        return false;
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