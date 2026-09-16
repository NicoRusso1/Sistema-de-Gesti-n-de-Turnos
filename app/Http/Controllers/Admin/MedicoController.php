<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMedicoRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MedicoController extends Controller
{
    /**
     * Formulario de alta de Medico. Uso exclusivo del Administrador.
     */
    public function create(): View
    {
        return view('admin.medicos.create');
    }

    /**
     * Da de alta un usuario con rol Medico.
     */
    public function store(StoreMedicoRequest $request): RedirectResponse
    {
        $medico = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
        ]);

        $medico->assignRole(RoleName::Medico->value);

        return redirect()
            ->route('admin.medicos.create')
            ->with('status', __('Medico registrado correctamente.'));
    }
}
