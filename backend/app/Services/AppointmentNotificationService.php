<?php

namespace App\Services;

use App\Enums\NotificationDeliveryStatus;
use App\Mail\CertificadoDigitalMail;
use App\Mail\RecordatorioTurnoMail;
use App\Mail\TurnoConfirmadoMail;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Payment;
use Closure;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AppointmentNotificationService
{
    public function notifyCancellation(Appointment $appointment, ?string $reason = null): void
    {
        $doctorName = $appointment->doctor
            ? "Dr/a. {$appointment->doctor->first_name} {$appointment->doctor->last_name}"
            : "su médico asignado";

        $formattedDate = $appointment->scheduled_at->format('d/m/Y H:i');
        $motivoTexto = $reason ? " Motivo: {$reason}." : "";

        $this->register([
            'patient_id'     => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo'           => 'cancelacion',
            'titulo'         => 'Turno cancelado',
            'mensaje'        => "Tu turno del {$formattedDate} con {$doctorName} ha sido cancelado.{$motivoTexto}",
        ]);

        if ($appointment->doctor_id && $appointment->doctor_id !== $appointment->patient_id) {
            $this->register([
                'patient_id'     => $appointment->doctor_id,
                'appointment_id' => $appointment->id,
                'tipo'           => 'cancelacion',
                'titulo'         => 'Turno cancelado en tu agenda',
                'mensaje'        => "El turno del {$formattedDate} ha sido cancelado y el horario quedó disponible.{$motivoTexto}",
            ]);
        }
    }

    public function notifyRescheduling(Appointment $appointment, string $previousDate): void
    {
        $doctorName = $appointment->doctor
            ? "Dr/a. {$appointment->doctor->first_name} {$appointment->doctor->last_name}"
            : "su médico";

        $newDate = $appointment->scheduled_at->format('d/m/Y H:i');

        $this->register([
            'patient_id'     => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo'           => 'reprogramacion',
            'titulo'         => 'Turno reprogramado',
            'mensaje'        => "Tu turno con {$doctorName} ha sido reprogramado del {$previousDate} al {$newDate}.",
        ]);
    }

    public function notifyReminder(Appointment $appointment): Notification
    {
        $doctorName = $appointment->doctor
            ? "Dr/a. {$appointment->doctor->first_name} {$appointment->doctor->last_name}"
            : "su médico asignado";

        $especialidad = $appointment->specialty?->name ?? 'Consulta General';
        $fecha = $appointment->scheduled_at->format('d/m/Y');
        $hora = $appointment->scheduled_at->format('H:i');
        $salaInfo = $appointment->sala ? " en Sala {$appointment->sala->numero} (Piso {$appointment->sala->piso})" : "";
        $email = $appointment->patient?->email;

        return $this->register([
            'patient_id'     => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo'           => 'recordatorio',
            'titulo'         => "Tu próximo turno es el {$fecha} a las {$hora} hs",
            'mensaje'        => "Recordá que tu próximo turno está agendado para el día {$fecha} a las {$hora} hs con {$doctorName} ({$especialidad}){$salaInfo}. Estado del turno: " . strtoupper($appointment->status) . ".",
        ], $email ? fn () => Mail::to($email)->send(new RecordatorioTurnoMail($appointment)) : null);
    }

    public function notifyPaymentConfirmed(Appointment $appointment, Payment $payment): Notification
    {
        $fecha = $appointment->scheduled_at->format('d/m/Y');
        $hora = $appointment->scheduled_at->format('H:i');
        $email = $appointment->patient?->email;

        return $this->register([
            'patient_id'     => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo'           => 'confirmacion',
            'titulo'         => 'Turno confirmado',
            'mensaje'        => "Se acreditó el pago de tu turno del {$fecha} a las {$hora} hs.",
        ], $email ? fn () => Mail::to($email)->send(new TurnoConfirmadoMail($appointment, $payment)) : null);
    }

    public function notifyCertificate(Appointment $appointment, string $fileName, string $viewUrl): Notification
    {
        $email = $appointment->patient?->email;

        return $this->register([
            'patient_id'     => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'tipo'           => 'certificado',
            'titulo'         => 'Certificado digital disponible',
            'mensaje'        => "Ya podés consultar el certificado de tu turno #{$appointment->id}.",
        ], $email ? fn () => Mail::to($email)->send(new CertificadoDigitalMail($appointment, $fileName, $viewUrl)) : null);
    }

    private function register(array $data, ?Closure $send = null): Notification
    {
        $notification = Notification::create($data + [
            'leido'        => false,
            'estado_envio' => NotificationDeliveryStatus::Pending,
        ]);

        try {
            if ($send) {
                $send();
            }

            $notification->update([
                'estado_envio' => NotificationDeliveryStatus::Sent,
                'error_envio'  => null,
                'enviada_at'   => now(),
            ]);
        } catch (Throwable $e) {
            logger()->error("Fallo envío de notificación #{$notification->id}: " . $e->getMessage());

            $notification->update([
                'estado_envio' => NotificationDeliveryStatus::Failed,
                'error_envio'  => $e->getMessage(),
            ]);
        }

        return $notification;
    }
}
