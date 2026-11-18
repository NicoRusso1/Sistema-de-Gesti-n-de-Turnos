<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Envía el recordatorio de turno a n8n, que se encarga de mandarlo por Telegram.
 * Solo se usa para las notificaciones de tipo "recordatorio".
 */
class TelegramReminderService
{
    public function isEnabled(): bool
    {
        return filled(config('services.n8n.reminder_webhook_url'));
    }

    /**
     * Devuelve true si n8n aceptó el mensaje. Nunca lanza excepción: Telegram es un
     * canal complementario y un fallo acá no tiene que afectar al email.
     */
    public function send(Appointment $appointment, Notification $notification): bool
    {
        $chatId = $appointment->patient?->telegram_chat_id;

        if (!$this->isEnabled() || !$chatId) {
            return false;
        }

        try {
            Http::timeout(10)
                ->withHeaders(['X-N8N-Secret' => (string) config('services.n8n.secret')])
                ->post(config('services.n8n.reminder_webhook_url'), $this->payload($appointment, $notification, $chatId))
                ->throw();

            return true;
        } catch (Throwable $e) {
            logger()->error("Fallo envío por Telegram (n8n) del recordatorio #{$notification->id}: " . $e->getMessage());
            return false;
        }
    }

    private function payload(Appointment $appointment, Notification $notification, string $chatId): array
    {
        $paciente = trim("{$appointment->patient->first_name} {$appointment->patient->last_name}");
        $medico = $appointment->doctor
            ? "Dr/a. {$appointment->doctor->first_name} {$appointment->doctor->last_name}"
            : 'su médico asignado';
        $especialidad = $appointment->specialty?->name ?? 'Consulta General';
        $fecha = $appointment->scheduled_at->format('d/m/Y');
        $hora = $appointment->scheduled_at->format('H:i');
        $sala = $appointment->sala ? "Sala {$appointment->sala->numero} (Piso {$appointment->sala->piso})" : null;

        $lineas = [
            "Hola {$appointment->patient->first_name}! Te recordamos tu próximo turno:",
            '',
            "Fecha: {$fecha}",
            "Hora: {$hora} hs",
            "Profesional: {$medico}",
            "Especialidad: {$especialidad}",
        ];

        if ($sala) {
            $lineas[] = "Lugar: {$sala}";
        }

        return [
            'chat_id'         => $chatId,
            'notification_id' => $notification->id,
            'appointment_id'  => $appointment->id,
            'paciente'        => $paciente,
            'medico'          => $medico,
            'especialidad'    => $especialidad,
            'fecha'           => $fecha,
            'hora'            => $hora,
            'sala'            => $sala,
            'texto'           => implode("\n", $lineas),
        ];
    }
}
