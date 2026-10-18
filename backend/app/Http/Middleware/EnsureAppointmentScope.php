<?php

namespace App\Http\Middleware;

use App\Models\Appointment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GT-54: valida el tipo_usuario y los flags de user_permissions para ver la agenda,
 * y deja resuelto el alcance (scope) que el controlador usa para filtrar la consulta.
 *
 * - Administrador / SuperAdmin, o flag view_all_appointments → scope "all"
 * - Médico con view_assigned_patients                          → scope "assigned"
 * - Paciente con view_own_appointments                         → scope "own"
 */
class EnsureAppointmentScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $scope = $this->resolveScope($request->user());

        if (!$scope) {
            return response()->json([
                'message' => 'No tenés permiso para ver la agenda de turnos'
            ], 403);
        }

        $request->attributes->set('appointment_scope', $scope);

        return $next($request);
    }

    private function resolveScope($user): ?string
    {
        if ($user->hasAdminAccess() || $user->hasPermissionFlag('view_all_appointments')) {
            return Appointment::SCOPE_ALL;
        }

        if ($user->isDoctor() && $user->hasPermissionFlag('view_assigned_patients')) {
            return Appointment::SCOPE_ASSIGNED;
        }

        if ($user->isPatientType() && $user->hasPermissionFlag('view_own_appointments')) {
            return Appointment::SCOPE_OWN;
        }

        return null;
    }
}
