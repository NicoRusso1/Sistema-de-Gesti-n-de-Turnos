<?php

namespace App\Observers;

use App\Enums\PaymentStatusName;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Services\AppointmentNotificationService;
use App\Services\CertificateService;
use App\Services\InvoiceService;

class PaymentObserver
{
    public function updated(Payment $payment): void
    {
        if (!$payment->wasChanged('payment_status_id')) {
            return;
        }

        $approved = PaymentStatus::where('name', PaymentStatusName::Approved->value)->first();

        if (!$approved || (int) $payment->payment_status_id !== (int) $approved->id) {
            return;
        }

        $appointment = $payment->appointment;

        if ($appointment->status === 'reservado') {
            $appointment->update(['status' => 'abonado']);
        }

        $certificatePath = null;
        $invoicePath = null;

        try {
            $certificatePath = app(CertificateService::class)->generate($appointment);
            $invoicePath = app(InvoiceService::class)->generate($appointment, $payment);
        } catch (\Throwable $e) {
            logger()->error("Error generando comprobantes del turno #{$appointment->id}: " . $e->getMessage());
        }

        // Email automático al paciente con la factura y el certificado adjuntos.
        // El resultado (enviada / fallida) queda registrado en la tabla notificaciones.
        app(AppointmentNotificationService::class)
            ->notifyPaymentConfirmed($appointment, $payment, $invoicePath, $certificatePath);
    }
}
