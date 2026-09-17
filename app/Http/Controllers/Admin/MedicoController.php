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
   
    public function create(): View
    {
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
}
}
