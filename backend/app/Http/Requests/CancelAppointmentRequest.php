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

        // Administradores y SuperAdmin tienen acceso completo
        if ($user->hasAdminAccess()) {
            return true;
        }

        // Secretarias u operarios con permiso 'cancel_appointments' o 'cancelar_turnos'
        if ($user->hasPermissionFlag('cancel_appointments') || $user->hasPermissionFlag('cancelar_turnos')) {
            return true;
        }

        // El paciente titular puede cancelar su propio turno
        $appointment = Appointment::find($this->route('id'));
        if ($appointment && $appointment->patient_id === $user->id) {
            return true;
        }

        return false;
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