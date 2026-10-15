<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientHealthInsuranceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'health_insurance_id' => [
                'required',
                'integer',
                Rule::exists('health_insurances', 'id')->where('status', 1),
            ],
            'member_number' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'health_insurance_id.exists' => 'La obra social seleccionada no existe o no está activa.',
            'member_number.required' => 'El número de afiliado es obligatorio.',
        ];
    }
}