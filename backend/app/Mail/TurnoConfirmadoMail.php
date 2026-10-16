<?php

namespace App\Mail;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TurnoConfirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public Payment $payment;

    public function __construct(Appointment $appointment, Payment $payment)
    {
        $this->appointment = $appointment->loadMissing(['doctor', 'specialty', 'healthInsurance', 'sala', 'patient']);
        $this->payment = $payment;
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
}