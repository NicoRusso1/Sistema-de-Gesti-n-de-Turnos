<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autenticado por Sanctum en la ruta
    }

    public function rules(): array
    {
        return [
            'doctor_id' => ['required', 'integer', 'exists:patients,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'scheduled_at' => [
                'required',
                'date_format:Y-m-d H:i',
                function ($attribute, $value, $fail) {
                    $date = Carbon::createFromFormat('Y-m-d H:i', $value);
                    if ($date->lt(now())) {
                        $fail('No se pueden reservar turnos en fechas u horas pasadas.');
                    }
                    if ($date->gt(now()->addYears(2))) {
                        $fail('No se pueden reservar turnos con más de 2 años de anticipación.');
                    }
                },
            ],
            'health_insurance_id' => ['nullable', 'integer', 'exists:health_insurances,id'],
            'affiliate_number' => ['nullable', 'required_with:health_insurance_id', 'string', 'max:50'],
            'monto_base' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'affiliate_number.required_with' => 'El número de afiliado es obligatorio si se selecciona una obra social.',
            'doctor_id.exists' => 'El médico seleccionado no existe.',
            'monto_base.required' => 'El monto base de la consulta es obligatorio.',
        ];
    }
}