<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TurnoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public Payment $payment;
    public ?string $invoicePath;
    public ?string $certificatePath;

    public function __construct(Appointment $appointment, Payment $payment, ?string $invoicePath = null, ?string $certificatePath = null)
    {
        $this->appointment = $appointment->loadMissing(['doctor', 'specialty', 'healthInsurance', 'sala', 'patient']);
        $this->payment = $payment;
        $this->invoicePath = $invoicePath;
        $this->certificatePath = $certificatePath;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmación y Comprobante de Turno Médico #' . $this->appointment->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.turno-confirmado',
        );
    }

    /**
     * Adjunta la factura y el certificado de turno si fueron generados.
     */
    public function attachments(): array
    {
        $files = [
            "Factura_Turno_{$this->appointment->id}.pdf"     => $this->invoicePath,
            "Certificado_Turno_{$this->appointment->id}.pdf" => $this->certificatePath,
        ];

        $attachments = [];

        foreach ($files as $name => $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                $attachments[] = Attachment::fromStorageDisk('public', $path)
                    ->as($name)
                    ->withMime('application/pdf');
            }
        }

        return $attachments;
    }
}
