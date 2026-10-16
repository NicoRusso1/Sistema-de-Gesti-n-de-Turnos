<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Patient;

class AppointmentNotificationService
{
    /**
     * Notificar la cancelación del turno al paciente y registrar en la BD (GT-59).
     */
    public function notifyCancellation(Appointment $appointment, ?string $reason = null): void
    {
        $doctorName = $appointment->doctor 
            ? "Dr/a. {$appointment->doctor->first_name} {$appointment->doctor->last_name}"
            : "su médico asignado";
            
        $formattedDate = $appointment->scheduled_at->format('d/m/Y H:i');
        $motivoTexto = $reason ? " Motivo: {$reason}." : "";

        Notification::create([
            'patient_id'     => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo'           => 'cancelacion',
            'titulo'         => 'Turno cancelado',
            'mensaje'        => "Tu turno del {$formattedDate} con {$doctorName} ha sido cancelado.{$motivoTexto}",
            'leido'          => false,
        ]);

        // Si el médico es un usuario distinto al que canceló, también se le puede notificar
        if ($appointment->doctor_id && $appointment->doctor_id !== $appointment->patient_id) {
            Notification::create([
                'patient_id'     => $appointment->doctor_id,
                'appointment_id' => $appointment->id,
                'tipo'           => 'cancelacion',
                'titulo'         => 'Turno cancelado en tu agenda',
                'mensaje'        => "El turno del {$formattedDate} ha sido cancelado y el horario quedó disponible.{$motivoTexto}",
                'leido'          => false,
            ]);
        }
    }

    /**
     * Notificar la reprogramación del turno al paciente y al médico (GT-59).
     */
    public function notifyRescheduling(Appointment $appointment, string $previousDate): void
    {
        $doctorName = $appointment->doctor 
            ? "Dr/a. {$appointment->doctor->first_name} {$appointment->doctor->last_name}"
            : "su médico";
            
        $newDate = $appointment->scheduled_at->format('d/m/Y H:i');

        Notification::create([
            'patient_id'     => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo'           => 'reprogramacion',
            'titulo'         => 'Turno reprogramado',
            'mensaje'        => "Tu turno con {$doctorName} ha sido reprogramado del {$previousDate} al {$newDate}.",
            'leido'          => false,
        ]);
    }
}