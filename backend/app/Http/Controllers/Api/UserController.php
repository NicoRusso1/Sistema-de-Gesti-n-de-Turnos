<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\UserType;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use App\Enums\UserTypeName;
use App\Models\UserPermission;

class UserController extends Controller
{
    #[OA\Get(
        path: '/api/users',
        summary: 'Listar usuarios con filtros',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['SuperAdmin', 'Administrator', 'OperationalUser'])),
            new OA\Parameter(name: 'user_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['patient', 'doctor', 'secretary'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado paginado de usuarios.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.')
        ]
    )]
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

    #[OA\Post(
        path: '/api/users',
        summary: 'Crear un usuario operativo',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'email', 'user_type_id', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'Ana'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Gomez'),
                    new OA\Property(property: 'email', type: 'string', example: 'ana@hospital.test'),
                    new OA\Property(property: 'phone', type: 'string', example: '3511234567'),
                    new OA\Property(property: 'user_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'password', type: 'string', example: 'Password123!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', example: 'Password123!')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario creado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
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

        $this->permissionsFor($user);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'user' => $user->load(['role', 'userType'])
        ], 201);
    }

    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Obtener un usuario operativo',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del usuario',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 2
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario encontrado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.')
        ]
    )]
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

    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'Actualizar un usuario operativo',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del usuario',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 2
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'Ana'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Gomez'),
                    new OA\Property(property: 'email', type: 'string', example: 'ana@hospital.test'),
                    new OA\Property(property: 'phone', type: 'string', example: '3511234567'),
                    new OA\Property(property: 'user_type_id', type: 'integer', example: 2)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Usuario actualizado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
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

        if ($user->wasChanged('user_type_id')) {
            $user->load('userType');
            $this->permissionsFor($user)->update($this->defaultPermissions($user->userType?->name));
            // Si deja de ser médico, pierde las especialidades asignadas
            if (!$user->isDoctor()) {
                $user->specialties()->detach();
            }
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'user' => $user->fresh()->load(['role', 'userType'])
        ], 200);
    }

    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Dar de baja un usuario operativo',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del usuario',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 2
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario dado de baja correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.')
        ]
    )]
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

    #[OA\Post(
        path: '/api/users/{id}/restore',
        summary: 'Reactivar un usuario operativo',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del usuario',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 2
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario reactivado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.')
        ]
    )]
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

    #[OA\Get(
        path: '/api/users/{id}/permissions',
        summary: 'Obtener los permisos de un usuario operativo',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del usuario',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 2
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Permisos obtenidos correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.')
        ]
    )]
    public function permissions(string $id)
    {
        $user = $this->findOperationalUser($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json($this->permissionsFor($user), 200);
    }

    #[OA\Put(
        path: '/api/users/{id}/permissions',
        summary: 'Actualizar los permisos de un usuario operativo',
        tags: ['Usuarios'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del usuario',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 2
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'view_own_appointments', type: 'boolean', example: false),
                    new OA\Property(property: 'view_all_appointments', type: 'boolean', example: false),
                    new OA\Property(property: 'view_assigned_patients', type: 'boolean', example: true),
                    new OA\Property(property: 'cancel_appointments', type: 'boolean', example: true),
                    new OA\Property(property: 'edit_schedules', type: 'boolean', example: false)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Permisos actualizados correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
    public function updatePermissions(Request $request, string $id)
    {
        $user = $this->findOperationalUser($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'view_own_appointments' => ['sometimes', 'boolean'],
            'view_all_appointments' => ['sometimes', 'boolean'],
            'view_assigned_patients' => ['sometimes', 'boolean'],
            'cancel_appointments' => ['sometimes', 'boolean'],
            'edit_schedules' => ['sometimes', 'boolean'],
        ]);

        if (($validated['view_assigned_patients'] ?? false) && !$user->isDoctor()) {
            return response()->json([
                'message' => 'El permiso view_assigned_patients solo aplica a médicos'
            ], 422);
        }

        $permissions = $this->permissionsFor($user);
        $permissions->update($validated);

        return response()->json([
            'message' => 'Permisos actualizados correctamente',
            'permissions' => $permissions->fresh()
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

    private function permissionsFor(Patient $user): UserPermission
    {
        return UserPermission::firstOrCreate(
            ['patient_id' => $user->id],
            $this->defaultPermissions($user->userType?->name)
        );
    }

    private function defaultPermissions(?string $typeName): array
    {
        return [
            'view_own_appointments' => $typeName === UserTypeName::Patient->value,
            'view_all_appointments' => $typeName === UserTypeName::Secretary->value,
            'view_assigned_patients' => $typeName === UserTypeName::Doctor->value,
            'cancel_appointments' => $typeName === UserTypeName::Secretary->value,
            'edit_schedules' => $typeName === UserTypeName::Secretary->value,
        ];
    }
}