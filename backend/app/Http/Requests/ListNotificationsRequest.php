<?php

namespace App\Http\Requests;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado'   => ['nullable', Rule::enum(NotificationDeliveryStatus::class)],
            'tipo'     => ['nullable', 'string', 'max:50'],
            'desde'    => ['nullable', 'date'],
            'hasta'    => ['nullable', 'date', 'after_or_equal:desde'],
            'paciente' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.enum'          => 'El estado debe ser pendiente, enviada o fallida.',
            'tipo.max'             => 'El tipo no puede exceder los 50 caracteres.',
            'desde.date'           => 'La fecha desde no es válida.',
            'hasta.date'           => 'La fecha hasta no es válida.',
            'hasta.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            'paciente.max'         => 'El filtro de paciente no puede exceder los 100 caracteres.',
        ];
    }
}
