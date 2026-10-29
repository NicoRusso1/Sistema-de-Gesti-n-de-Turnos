<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CertificadoDigitalMail extends Mailable
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public string $filePath;

    public function __construct(Appointment $appointment, string $filePath)
    {
        $this->appointment = $appointment->loadMissing(['doctor', 'specialty', 'patient']);
        $this->filePath = $filePath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu Certificado Médico Digital - Turno #{$this->appointment->id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "<p>Hola <strong>{$this->appointment->patient?->first_name}</strong>,</p><p>Adjuntamos a este correo tu certificado médico digital correspondiente a la atención del día {$this->appointment->scheduled_at->format('d/m/Y')}.</p><p>Saludos cordiales,<br>Equipo de Atención Médica</p>",
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('public', $this->filePath)
                ->as("Certificado_Turno_{$this->appointment->id}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}