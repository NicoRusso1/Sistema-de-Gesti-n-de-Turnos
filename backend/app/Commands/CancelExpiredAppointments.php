<?php

namespace App\Commands;

use App\Models\Appointment;
use App\Services\AppointmentNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CancelExpiredAppointments extends Command
{
    protected $signature = 'turnos:cancelar-expirados';
    protected $description = 'Cancela turnos que llevan más de 1 hora reservados sin pago y libera la agenda/sala';

    public function handle(AppointmentNotificationService $notificationService): int
    {
        $limiteTiempo = Carbon::now()->subHour();

        $turnosExpirados = Appointment::with(['doctor', 'patient', 'sala'])
            ->where('status', 'reservado')
            ->where('created_at', '<=', $limiteTiempo)
            ->get();

        if ($turnosExpirados->isEmpty()) {
            $this->info('No hay turnos expirados para cancelar.');
            return Command::SUCCESS;
        }

        $contador = 0;

        foreach ($turnosExpirados as $appointment) {
            $appointment->update([
                'status'              => 'cancelado',
                'cancellation_reason' => 'Cancelación automática: se superó el límite de 1 hora para abonar la reserva.',
            ]);

            // Generar notificación para el paciente
            $notificationService->notifyCancellation(
                $appointment,
                'Cancelado automáticamente por falta de pago (plazo de 1 hora vencido)'
            );

            $this->line("Turno #{$appointment->id} cancelado. Agenda del médico #{$appointment->doctor_id} liberada.");
            $contador++;
        }

        $this->info("Proceso finalizado. Total de turnos cancelados: {$contador}");
        return Command::SUCCESS;
    }
}