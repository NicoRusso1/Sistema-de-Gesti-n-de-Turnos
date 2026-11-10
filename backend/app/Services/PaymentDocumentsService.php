<?php

namespace App\Services;

use App\Enums\DocumentIssueStatus;
use App\Enums\NotificationDeliveryStatus;
use App\Models\Certificate;
use App\Models\Notification;
use App\Models\Payment;
use App\Support\Documents;
use Throwable;

/**
 * Emite la factura y el certificado de un pago aprobado y los envía por email.
 * Se usa al aprobarse el pago y también para reintentar cuando algo falló:
 * solo regenera los PDF que faltan y solo reenvía el mail si no salió.
 */
class PaymentDocumentsService
{
    public function __construct(
        private CertificateService $certificates,
        private InvoiceService $invoices,
        private AppointmentNotificationService $notifications,
    ) {
    }

    public function process(Payment $payment): Certificate
    {
        $appointment = $payment->appointment;
        $certificate = Certificate::firstOrNew(['appointment_id' => $appointment->id]);

        if (!$this->documentsIssued($certificate)) {
            $certificate->fill([
                'estado_emision'    => DocumentIssueStatus::Pending,
                'ultimo_intento_at' => now(),
            ])->save();

            try {
                if (!Documents::exists($certificate->certificate_url)) {
                    $this->certificates->generate($appointment);
                }

                if (!Documents::exists($certificate->fresh()->invoice_url)) {
                    $this->invoices->generate($appointment, $payment);
                }

                $certificate->refresh()->update([
                    'estado_emision' => DocumentIssueStatus::Issued,
                    'error_emision'  => null,
                ]);
            } catch (Throwable $e) {
                logger()->error("Error emitiendo comprobantes del turno #{$appointment->id}: " . $e->getMessage());

                $certificate->refresh()->update([
                    'estado_emision' => DocumentIssueStatus::Failed,
                    'error_emision'  => $e->getMessage(),
                ]);

                // Queda visible en el historial de notificaciones para reintentarlo
                $this->notifications->notifyPaymentConfirmationFailed(
                    $appointment,
                    'No se pudo emitir la factura: ' . $e->getMessage()
                );

                return $certificate;
            }
        }

        if (!$this->confirmationSent($payment)) {
            $this->notifications->notifyPaymentConfirmed(
                $appointment,
                $payment,
                $certificate->invoice_url,
                $certificate->certificate_url,
            );
        }

        return $certificate->refresh();
    }

    /**
     * Un pago está completo cuando tiene los PDF emitidos y el email enviado.
     */
    public function isComplete(Payment $payment): bool
    {
        $certificate = Certificate::where('appointment_id', $payment->appointment_id)->first();

        return $certificate && $this->documentsIssued($certificate) && $this->confirmationSent($payment);
    }

    public function failedAttempts(Payment $payment): int
    {
        return Notification::where('appointment_id', $payment->appointment_id)
            ->where('tipo', 'confirmacion')
            ->where('estado_envio', NotificationDeliveryStatus::Failed->value)
            ->count();
    }

    private function documentsIssued(Certificate $certificate): bool
    {
        return $certificate->exists
            && $certificate->estado_emision === DocumentIssueStatus::Issued
            && Documents::exists($certificate->invoice_url)
            && Documents::exists($certificate->certificate_url);
    }

    private function confirmationSent(Payment $payment): bool
    {
        return Notification::where('appointment_id', $payment->appointment_id)
            ->where('tipo', 'confirmacion')
            ->where('estado_envio', NotificationDeliveryStatus::Sent->value)
            ->exists();
    }
}
