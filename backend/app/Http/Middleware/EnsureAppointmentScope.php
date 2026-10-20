<?php

namespace App\Http\Middleware;

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
        $scope = $request->user()->appointmentScope();

        if (!$scope) {
            return response()->json([
                'message' => 'No tenés permiso para ver la agenda de turnos'
            ], 403);
        }

        $request->attributes->set('appointment_scope', $scope);

        return $next($request);
    }
}
