<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMedicoRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MedicoController extends Controller
{
    /**
     * Listado de médicos para el Dashboard.
     */
    public function index(): View
    {
        $this->authorizeAccess();

        $medicos = User::role(RoleName::Medico->value)->get();

        return view('admin.medicos.index', compact('medicos'));
    }

    /**
     * Formulario de alta de Medico.
     */
    public function create(): View
    {
        $this->authorizeAccess();

        return view('admin.medicos.create');
    }

    /**
     * Da de alta un usuario con rol Medico.
     */
    public function store(StoreMedicoRequest $request): RedirectResponse
    {
        $this->authorizeAccess();

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

    /**
     * Actualiza la información del médico (Admin y SuperAdmin).
     */
    public function update(Request $request, User $medico): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $medico->id],
        ]);

        $medico->update($validated);

        return redirect()
            ->back()
            ->with('status', __('Médico actualizado correctamente.'));
    }

    /**
     * Verifica que el usuario tenga rol Admin o SuperAdmin.
     */
    private function authorizeAccess(): void
    {
        if (!Auth::user()->hasAnyRole([RoleName::Admin->value, RoleName::SuperAdmin->value ?? 'SuperAdmin'])) {
            abort(403, 'No tienes permisos para gestionar médicos.');
        }
    }
}