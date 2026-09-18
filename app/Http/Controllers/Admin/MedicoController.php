<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMedicoRequest;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MedicoController extends Controller
{
    public function create(): View
    {
        return view('admin.medicos.create');
    }

    public function store(StoreMedicoRequest $request): RedirectResponse
    {
        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $doctorType = UserType::where('name', UserTypeName::Doctor->value)->firstOrFail();

        Patient::create([
            'first_name' => $request->validated('first_name'),
            'last_name' => $request->validated('last_name'),
            'email' => $request->validated('email'),
            'password_hash' => Hash::make($request->validated('password')),
            'role_id' => $role->id,
            'user_type_id' => $doctorType->id,
            'status' => 1,
        ]);

        return redirect()
            ->route('admin.medicos.create')
            ->with('status', __('Medico registrado correctamente.'));
    }
}