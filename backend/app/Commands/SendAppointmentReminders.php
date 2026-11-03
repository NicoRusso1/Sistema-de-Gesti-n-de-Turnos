<?php

namespace App\Commands;

use App\Enums\NotificationDeliveryStatus;
use App\Models\Appointment;
use App\Models\Notification;
use App\Services\AppointmentNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'turnos:enviar-recordatorios';
    protected $description = 'Envía recordatorios por email y genera notificación interna para los turnos del día siguiente';

    public function handle(AppointmentNotificationService $notificationService): int
    {
        // Rango de búsqueda: turnos programados para el día de mañana
        $mananaInicio = Carbon::tomorrow()->startOfDay();
        $mananaFin    = Carbon::tomorrow()->endOfDay();

        $turnos = Appointment::with(['doctor', 'patient', 'specialty', 'sala'])
            ->whereIn('status', ['abonado', 'reservado', 'scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [$mananaInicio, $mananaFin])
            ->get();

        if ($turnos->isEmpty()) {
            $this->info('No hay turnos agendados para mañana.');
            return Command::SUCCESS;
        }

        $enviados = 0;

        foreach ($turnos as $appointment) {
            $email = $appointment->patient?->email;

            if (!$email) {
                continue;
            }

            // Evitar enviar recordatorios duplicados si el comando corre más de una vez
            $yaNotificado = Notification::where('appointment_id', $appointment->id)
                ->where('tipo', 'recordatorio')
                ->where('estado_envio', '!=', NotificationDeliveryStatus::Failed->value)
                ->exists();

            if ($yaNotificado) {
                continue;
            }

            $notification = $notificationService->notifyReminder($appointment);

            if ($notification->estado_envio === NotificationDeliveryStatus::Sent) {
                $this->line("Recordatorio enviado al paciente {$email} para el turno #{$appointment->id}.");
                $enviados++;
            } else {
                $this->error("Error enviando recordatorio al turno #{$appointment->id}: {$notification->error_envio}");
            }
        }

        $this->info("Proceso finalizado. Total de recordatorios enviados: {$enviados}");
        return Command::SUCCESS;
    }
}