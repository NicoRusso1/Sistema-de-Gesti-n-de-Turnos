<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::with(['role', 'userType', 'personalData']);

  
        if ($request->filled('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

  
        if ($request->filled('user_type')) {
            $query->whereHas('userType', function ($q) use ($request) {
                $q->where('name', $request->user_type);
            });
        }

     
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

    
        if ($request->filled('search')) {
            $search = $request->search;
            
            $query->where(function ($q) use ($search) {
               
                $terms = explode(' ', $search);
                
                foreach ($terms as $term) {
                    $q->where(function ($subQuery) use ($term) {
                        $subQuery->where('first_name', 'like', "%{$term}%")
                                 ->orWhere('last_name', 'like', "%{$term}%")
                                 ->orWhere('email', 'like', "%{$term}%")
                                 ->orWhereHas('personalData', function ($qData) use ($term) {
                                     $qData->where('national_id', 'like', "%{$term}%");
                                 });
                    });
                }
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(15);

        return response()->json($users);
    }

   public function store(Request $request)
{
    $request->validate([
        'first_name' => ['required', 'string', 'max:255'],
        'last_name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:patients,email'],
        'phone' => ['nullable', 'string', 'max:255'],
        'user_type_id' => ['required', 'integer', 'exists:user_types,id'],
        'password' => ['required', 'confirmed', Password::defaults()],
    ], [
        'email.unique' => 'El correo electrónico ya se encuentra registrado.',
    ]);

    $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();

    $user = Patient::create([
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'email' => strtolower($request->email),
        'phone' => $request->phone,
        'user_type_id' => $request->user_type_id,
        'password_hash' => Hash::make($request->password),
        'role_id' => $role->id,
        'status' => 1,
    ]);

    return response()->json([
        'message' => 'Usuario creado correctamente',
        'user' => $user->load(['role', 'userType'])
    ], 201);
}

    public function show(string $id)
{
    $user = $this->findOperationalUser($id);

    if (!$user) {
        return response()->json([
            'message' => 'Usuario no encontrado'
        ], 404);
    }

    return response()->json($user->load('personalData'), 200);
}

public function update(Request $request, string $id)
{
    $user = $this->findOperationalUser($id);

    if (!$user) {
        return response()->json([
            'message' => 'Usuario no encontrado'
        ], 404);
    }

    $validated = $request->validate([
        'first_name' => ['sometimes', 'required', 'string', 'max:255'],
        'last_name' => ['sometimes', 'required', 'string', 'max:255'],
        'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('patients', 'email')->ignore($user->id)],
        'phone' => ['nullable', 'string', 'max:255'],
        'user_type_id' => ['sometimes', 'required', 'integer', 'exists:user_types,id'],
    ], [
        'email.unique' => 'El correo electrónico ya se encuentra registrado.',
    ]);

    if (isset($validated['email'])) {
        $validated['email'] = strtolower($validated['email']);
    }

    $user->update($validated);

    return response()->json([
        'message' => 'Usuario actualizado correctamente',
        'user' => $user->fresh()->load(['role', 'userType'])
    ], 200);
}

    public function destroy(string $id)
    {
        $user = $this->findOperationalUser($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $user->status = 0; 
        $user->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Usuario dado de baja correctamente'
        ], 200);

    }

    public function restore(string $id)
    {
        $user = $this->findOperationalUser($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $user->status = 1; 
        $user->save();

        return response()->json([
            'message' => 'Usuario reactivado correctamente'
        ], 200);

    }

    private function findOperationalUser(string $id): ?Patient
    {
        return Patient::with(['role', 'userType'])
        ->whereHas('role', function ($query) {
            $query->where('name', RoleName::OperationalUser->value);
        })
        ->find($id);
    }
}
