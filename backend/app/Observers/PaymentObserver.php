<?php

namespace App\Observers;

use App\Enums\PaymentStatusName;
use App\Models\Payment;
use App\Models\PaymentStatus;
use App\Services\PaymentDocumentsService;

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

        // Emite factura y certificado y los envía por email. Si algo falla queda
        // registrado y el cron facturas:reintentar lo vuelve a intentar.
        app(PaymentDocumentsService::class)->process($payment);
    }
}
