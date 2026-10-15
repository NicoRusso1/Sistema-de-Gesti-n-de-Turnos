<?php

namespace App\Observers;

use App\Enums\PaymentStatusName;
use App\Models\Payment;
use App\Models\PaymentStatus;

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
    }
}