<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleName;
use App\Enums\UserTypeName;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:patients,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'email.unique' => 'El correo electrónico ya se encuentra registrado',
        ]);

        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();
        $patientType = UserType::where('name', UserTypeName::Patient->value)->firstOrFail();

        $patient = Patient::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => strtolower($request->email),
            'password_hash' => Hash::make($request->password),
            'role_id' => $role->id,
            'user_type_id' => $patientType->id,
            'status' => 1,
        ]);

        return response()->json([
            'message' => 'Usuario registrado correctamente',
            'patient' => $patient
        ], 201);
    }
}