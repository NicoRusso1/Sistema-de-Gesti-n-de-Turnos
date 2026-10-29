<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecordatorioTurnoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Appointment $appointment;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment->loadMissing(['doctor', 'specialty', 'sala', 'patient', 'healthInsurance']);
    }

    public function envelope(): Envelope
    {
        $fecha = $this->appointment->scheduled_at->format('d/m/Y H:i');
        return new Envelope(
            subject: "Recordatorio de Turno Médico - {$fecha} hs",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recordatorio-turno',
        );
    }
}