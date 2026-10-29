<?php

namespace App\Commands;

use App\Mail\RecordatorioTurnoMail;
use App\Models\Appointment;
use App\Models\Notification;
use App\Services\AppointmentNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

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
                ->exists();

            if ($yaNotificado) {
                continue;
            }

            try {
                // 1. Enviar correo electrónico
                Mail::to($email)->send(new RecordatorioTurnoMail($appointment));

                // 2. Registrar notificación interna en el sistema
                $notificationService->notifyReminder($appointment);

                $this->line("Recordatorio enviado al paciente {$email} para el turno #{$appointment->id}.");
                $enviados++;
            } catch (\Throwable $e) {
                $this->error("Error enviando recordatorio al turno #{$appointment->id}: " . $e->getMessage());
                logger()->error("Fallo envío recordatorio turno #{$appointment->id}: " . $e->getMessage());
            }
        }

        $this->info("Proceso finalizado. Total de recordatorios enviados: {$enviados}");
        return Command::SUCCESS;
    }
}