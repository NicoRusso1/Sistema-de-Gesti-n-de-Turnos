<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class StoreMedicoRequest extends FormRequest
{
    /**
     * Solo un Administrador puede dar de alta un Medico.
     * La ruta ya esta protegida por el middleware 'role:administrador',
     * esta comprobacion queda como segunda capa de seguridad.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Administrador->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }
}
