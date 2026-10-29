<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/me/notifications',
        summary: 'Notificaciones del usuario autenticado',
        description: 'Retorna las últimas 10 notificaciones del usuario logueado, ordenadas por fecha descendente.',
        tags: ['Notificaciones'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de notificaciones.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'mensaje', type: 'string', example: 'Recordatorio: tenés un turno mañana.'),
                            new OA\Property(property: 'leido', type: 'boolean', example: false),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    /**
     * Notificaciones del usuario logueado (para ver en el Inicio / Home).
     */
    public function myNotifications(Request $request): JsonResponse
    {
        $notifications = Notification::where('patient_id', $request->user()->id)
            ->with('appointment.doctor', 'appointment.specialty')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json($notifications, 200);
    }

    #[OA\Patch(
        path: '/api/notifications/{id}/read',
        summary: 'Marcar notificación como leída',
        tags: ['Notificaciones'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notificación marcada como leída.'),
            new OA\Response(response: 404, description: 'Notificación no encontrada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    /**
     * Marcar notificación como leída.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $notification = Notification::where('patient_id', $request->user()->id)
            ->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Notificación no encontrada'], 404);
        }

        $notification->update(['leido' => true]);

        return response()->json(['message' => 'Notificación marcada como leída'], 200);
    }

    #[OA\Get(
        path: '/api/notifications',
        summary: 'Historial global de notificaciones (Médicos y Administradores)',
        description: 'Los administradores ven todas las notificaciones. Los médicos solo ven las de sus turnos asignados.',
        tags: ['Notificaciones'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista paginada de notificaciones.'),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    /**
     * Historial global de notificaciones (Exclusivo Médicos / Admins).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isDoctor = $user->isDoctor();
        $isAdmin = $user->hasAdminAccess();

        if (!$isDoctor && !$isAdmin) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $query = Notification::with(['patient', 'appointment.doctor', 'appointment.specialty'])
            ->orderBy('created_at', 'desc');

        // Si es médico y no admin, solo ve las de sus turnos
        if ($isDoctor && !$isAdmin) {
            $query->whereHas('appointment', function ($q) use ($user) {
                $q->where('doctor_id', $user->id);
            });
        }

        return response()->json($query->paginate(15), 200);
    }

    #[OA\Post(
        path: '/api/notifications/send-reminders',
        summary: 'Disparar envío de recordatorios de turno (Médicos y Admins)',
        description: 'Ejecuta el comando Artisan que envía recordatorios por email a los pacientes con turnos al día siguiente.',
        tags: ['Notificaciones'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Recordatorios procesados.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Recordatorios procesados exitosamente'),
                        new OA\Property(property: 'output', type: 'string', example: '2 recordatorio(s) enviado(s).'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'No autorizado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    /**
     * Disparar el comando de recordatorios desde el Frontend (Médicos y Admins).
     */
    public function sendReminders(Request $request): JsonResponse
    {
        $user = $request->user();
        $isDoctor = $user->isDoctor();
        $isAdmin = $user->hasAdminAccess();

        if (!$isDoctor && !$isAdmin) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        // Ejecuta el comando Artisan de recordatorios
        Artisan::call('turnos:enviar-recordatorios');
        $output = Artisan::output();

        return response()->json([
            'message' => 'Recordatorios procesados exitosamente',
            'output'  => trim($output) ?: 'No hay turnos pendientes de recordatorio para mañana.',
        ], 200);
    }

    #[OA\Delete(
        path: '/api/notifications/{id}',
        summary: 'Eliminar una notificación',
        tags: ['Notificaciones'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notificación eliminada.'),
            new OA\Response(response: 404, description: 'Notificación no encontrada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    /**
     * Eliminar una notificación.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $notification = Notification::where('patient_id', $request->user()->id)->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Notificación no encontrada'], 404);
        }

        $notification->delete();

        return response()->json(['message' => 'Notificación eliminada'], 200);
    }
}
