<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignSalaRequest extends FormRequest
{
    // GT-43: solo la Secretaria y el Administrador (o SuperAdmin) asignan salas a mano
    public function authorize(): bool
    {
        $user = $this->user();

        return $user && ($user->hasAdminAccess() || $user->isSecretary());
    }

    public function rules(): array
    {
        return [
            'sala_id' => ['required', 'integer', 'exists:salas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'sala_id.required' => 'Tenés que seleccionar una sala.',
            'sala_id.exists'   => 'La sala seleccionada no existe.',
        ];
    }
}
