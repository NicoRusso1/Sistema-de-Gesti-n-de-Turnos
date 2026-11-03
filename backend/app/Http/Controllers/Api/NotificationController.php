<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListNotificationsRequest;
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
                description: 'Lista de las últimas 10 notificaciones del usuario, con el estado del envío.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Notificacion'))
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])
            ),
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
            new OA\Response(
                response: 200,
                description: 'Notificación marcada como leída.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Notificación marcada como leída')])
            ),
            new OA\Response(
                response: 404,
                description: 'Notificación no encontrada.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Notificación no encontrada')])
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])
            ),
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
        description: 'Los administradores ven todas las notificaciones. Los médicos solo ven las de sus turnos asignados. Incluye el estado del envío (pendiente, enviada, fallida) y admite filtros opcionales.',
        tags: ['Notificaciones'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'estado', in: 'query', required: false, description: 'Estado del envío.', schema: new OA\Schema(type: 'string', enum: ['pendiente', 'enviada', 'fallida'])),
            new OA\Parameter(name: 'tipo', in: 'query', required: false, description: 'Tipo de notificación (cancelacion, reprogramacion, recordatorio, confirmacion, certificado).', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'desde', in: 'query', required: false, description: 'Fecha de emisión desde (inclusive).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'hasta', in: 'query', required: false, description: 'Fecha de emisión hasta (inclusive).', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'paciente', in: 'query', required: false, description: 'Nombre, apellido o email del paciente.', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista paginada (15 por página) de notificaciones con el estado del envío.',
                content: new OA\JsonContent(ref: '#/components/schemas/NotificacionesPaginadas')
            ),
            new OA\Response(
                response: 403,
                description: 'El usuario no es médico ni administrador.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'No autorizado')])
            ),
            new OA\Response(
                response: 422,
                description: 'Filtros inválidos.',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorValidacion')
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])
            ),
        ]
    )]
    /**
     * Historial global de notificaciones (Exclusivo Médicos / Admins).
     */
    public function index(ListNotificationsRequest $request): JsonResponse
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

        $query->when($request->filled('estado'), fn ($q) => $q->where('estado_envio', $request->input('estado')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('desde')))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('hasta')))
            ->when($request->filled('paciente'), function ($q) use ($request) {
                $texto = '%' . $request->input('paciente') . '%';
                $q->whereHas('patient', function ($p) use ($texto) {
                    $p->where('first_name', 'like', $texto)
                        ->orWhere('last_name', 'like', $texto)
                        ->orWhere('email', 'like', $texto);
                });
            });

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
            new OA\Response(
                response: 403,
                description: 'El usuario no es médico ni administrador.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'No autorizado')])
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])
            ),
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
            new OA\Response(
                response: 200,
                description: 'Notificación eliminada.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Notificación eliminada')])
            ),
            new OA\Response(
                response: 404,
                description: 'Notificación no encontrada.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Notificación no encontrada')])
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])
            ),
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
