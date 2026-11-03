<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function generate(Appointment $appointment, Payment $payment): string
    {
        $appointment->loadMissing(['doctor', 'specialty', 'sala', 'patient', 'healthInsurance']);
        $payment->loadMissing('method');
        $issueDate = now();

        $pdf = Pdf::loadView('invoices.invoice', [
            'appointment' => $appointment,
            'payment' => $payment,
            'issueDate' => $issueDate,
        ])->setPaper('a4', 'portrait');

        $fileName = "facturas/factura_turno_{$appointment->id}_" . time() . '.pdf';
        Storage::disk('public')->put($fileName, $pdf->output());

        $certificate = Certificate::firstOrNew(['appointment_id' => $appointment->id]);
        $certificate->invoice_url = $fileName;
        $certificate->issue_date = $certificate->issue_date ?? $issueDate;
        $certificate->save();

        return $fileName;
    }
}
