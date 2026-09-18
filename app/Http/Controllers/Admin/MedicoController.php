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
<<<<<<< HEAD
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
=======
   
>>>>>>> ab0581a9936b3ddc754549f1c7245050ea5bf45d
    public function create(): View
    {
        $this->authorizeAccess();

        return view('admin.medicos.create');
    }

  
    <?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
<<<<<<< HEAD
        $this->authorizeAccess();

        $medico = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
=======
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.Patient::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
>>>>>>> ab0581a9936b3ddc754549f1c7245050ea5bf45d
        ]);

        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $patientType = UserType::where('name', UserTypeName::Patient->value)->firstOrFail();

        $user = Patient::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'role_id' => $role->id,
            'user_type_id' => $patientType->id,
            'status' => 1,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
<<<<<<< HEAD

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
=======
}
}
>>>>>>> ab0581a9936b3ddc754549f1c7245050ea5bf45d
