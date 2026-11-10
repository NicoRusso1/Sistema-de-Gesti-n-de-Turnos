<?php

namespace App\Commands;

use App\Enums\NotificationDeliveryStatus;
use App\Models\Appointment;
use App\Models\Notification;
use App\Services\AppointmentNotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'turnos:enviar-recordatorios {--horas= : Plazo en horas antes del turno (por defecto config notifications.reminder_hours_before)}';
    protected $description = 'Envía recordatorios por email a los pacientes con turnos dentro del plazo configurado (ej. 24 hs antes)';

    public function handle(AppointmentNotificationService $notificationService): int
    {
        $horas = (int) ($this->option('horas') ?: config('notifications.reminder_hours_before', 24));

        if ($horas <= 0) {
            $this->error('El plazo en horas tiene que ser mayor a cero.');
            return Command::FAILURE;
        }

        // Turnos que empiezan entre ahora y dentro de N horas
        $desde = now();
        $hasta = now()->addHours($horas);

        $turnos = Appointment::with(['doctor', 'patient', 'specialty', 'sala'])
            ->whereIn('status', ['abonado', 'reservado', 'scheduled', 'confirmed'])
            ->whereBetween('scheduled_at', [$desde, $hasta])
            ->get();

        if ($turnos->isEmpty()) {
            $this->info("No hay turnos agendados en las próximas {$horas} hs.");
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
