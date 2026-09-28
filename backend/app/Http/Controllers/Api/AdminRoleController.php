<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;
use App\Enums\AdminActionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\Patient;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRoleController extends Controller
{
    #[OA\Post(
        path: '/api/users/{id}/admin-role',
        summary: 'Conceder el rol de Administrador a un usuario',
        tags: ['Roles administrativos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2)
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rol de Administrador concedido correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Solo SuperAdmin o Administrador Propietario.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario ya es administrador o está dado de baja.')
        ]
    )]
    public function grant(Request $request, string $id)
    {
        $target = Patient::with('role')->find($id);

        if (!$target) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if ($target->status !== 1) {
            return response()->json(['message' => 'No se puede conceder el rol a un usuario dado de baja'], 422);
        }

        if ($target->hasAdminAccess()) {
            return response()->json(['message' => 'El usuario ya tiene rol de Administrador'], 422);
        }

        $role = Role::where('name', RoleName::Administrator->value)->firstOrFail();

        DB::transaction(function () use ($request, $target, $role) {
            $target->update(['role_id' => $role->id]);
            AdminActionLog::record($request->user(), $target, AdminActionName::GrantAdmin);
        });

        return response()->json([
            'message' => 'Rol de Administrador concedido correctamente',
            'user' => $target->fresh()->load(['role', 'userType'])
        ], 200);
    }

    #[OA\Delete(
        path: '/api/users/{id}/admin-role',
        summary: 'Revocar el rol de Administrador a un usuario',
        tags: ['Roles administrativos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del usuario', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 2)
        ],
        responses: [
            new OA\Response(response: 200, description: 'Rol de Administrador revocado correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos, o el afectado es Propietario y quien revoca no es SuperAdmin.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario no es administrador o intenta revocarse a sí mismo.')
        ]
    )]
    public function revoke(Request $request, string $id)
    {
        $actor = $request->user();
        $target = Patient::with('role')->find($id);

        if (!$target) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if (!$target->isAdministrator()) {
            return response()->json(['message' => 'El usuario no tiene rol de Administrador'], 422);
        }

        if ($target->id === $actor->id) {
            return response()->json(['message' => 'No podés revocarte el rol a vos mismo'], 422);
        }

        if ($target->is_owner && !$actor->isSuperAdmin()) {
            return response()->json([
                'message' => 'Solo el Super Administrador puede revocar el rol a un Administrador Propietario'
            ], 403);
        }

        $role = Role::where('name', RoleName::OperationalUser->value)->firstOrFail();

        DB::transaction(function () use ($actor, $target, $role) {
            $target->update([
                'role_id' => $role->id,
                'is_owner' => false,
            ]);
            AdminActionLog::record($actor, $target, AdminActionName::RevokeAdmin);
        });

        // Cierra sesiones activas para que no conserve privilegios en el front
        $target->tokens()->delete();

        return response()->json([
            'message' => 'Rol de Administrador revocado correctamente',
            'user' => $target->fresh()->load(['role', 'userType'])
        ], 200);
    }
}
