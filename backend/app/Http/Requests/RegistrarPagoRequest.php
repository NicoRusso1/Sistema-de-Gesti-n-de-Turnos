<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'turno_id'    => ['required', 'integer', 'exists:appointments,id'],
            'token_pago'  => ['required', 'string'],
            'metodo_pago' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'turno_id.required'   => 'El identificador del turno es obligatorio.',
            'turno_id.exists'     => 'El turno indicado no existe.',
            'token_pago.required' => 'El token seguro de pago es requerido.',
        ];
    }
}