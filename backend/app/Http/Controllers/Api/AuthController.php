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
use Illuminate\Validation\ValidationException;

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

    /**
     * GT: login vía API con Sanctum (Bearer token).
     * Angular manda email/password, si son correctos le devolvemos un
     * token que va a mandar en el header Authorization de ahí en más.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $patient = Patient::where('email', strtolower($request->email))->first();

        if (! $patient || ! Hash::check($request->password, $patient->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        // Invalida tokens anteriores del mismo dispositivo/sesión (opcional,
        // pero evita ir acumulando tokens infinitos en cada login).
        $patient->tokens()->delete();

        $token = $patient->createToken('angular-frontend')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'token' => $token,
            'patient' => $patient,
        ]);
    }

    /**
     * Logout: invalida el token actual.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }
}