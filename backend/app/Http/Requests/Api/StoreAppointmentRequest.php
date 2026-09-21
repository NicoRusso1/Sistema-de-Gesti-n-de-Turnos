<?php

namespace App\Http\Requests\Api;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas de negocio (Sección 2 y 5 del documento):
 * - Horario de atención: 08:00 a 20:00 hs.
 * - Se puede reservar desde hoy hasta un máximo de 2 años a futuro.
 * - Duración fija de cada turno: 15 minutos.
 * - Un médico no puede tener dos turnos superpuestos el mismo día.
 * - Un paciente solo puede reservar para sí mismo; el personal
 *   (Secretaria/Administrador/SuperAdmin) puede reservar para cualquier
 *   paciente.
 */
class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $actor */
        $actor = $this->user();

        $isStaff = $actor->isSuperAdmin()
            || $actor->isAdministrator()
            || $actor->hasPermissionFlag('edit_schedules');

        // Un paciente solo puede reservar su propio turno.
        if (! $isStaff && (int) $this->input('patient_id') !== $actor->id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')],
            'doctor_id' => ['required', 'integer', Rule::exists('patients', 'id')],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'start_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'No se pueden reservar turnos en fechas pasadas.',
            'date.before_or_equal' => 'Los turnos se pueden reservar hasta con 2 años de anticipación.',
        ];
    }

    /**
     * Validaciones que dependen de más de un campo a la vez
     * (horario de atención y superposición de turnos).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $startTime = $this->input('start_time');

            if (! $startTime) {
                return;
            }

            $start = Carbon::createFromFormat('H:i', $startTime);
            $openTime = Carbon::createFromFormat('H:i', '08:00');
            $closeTime = Carbon::createFromFormat('H:i', '20:00');
            $end = $start->copy()->addMinutes(15);

            if ($start->lt($openTime) || $end->gt($closeTime)) {
                $validator->errors()->add(
                    'start_time',
                    'El horario de atención es de 08:00 a 20:00 hs.'
                );

                return;
            }

            $overlaps = Appointment::where('doctor_id', $this->input('doctor_id'))
                ->where('date', $this->input('date'))
                ->where('start_time', $start->format('H:i:s'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add(
                    'start_time',
                    'El médico ya tiene un turno reservado en ese horario.'
                );
            }
        });
    }
}
