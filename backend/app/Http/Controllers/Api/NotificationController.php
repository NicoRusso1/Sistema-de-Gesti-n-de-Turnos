<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class NotificationController extends Controller
{
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