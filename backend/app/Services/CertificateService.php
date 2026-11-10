<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Support\Documents;
use Illuminate\Support\Str;

class CertificateService
{
    public function generate(Appointment $appointment, ?string $observaciones = null, ?Carbon $fechaEmision = null): string
    {
        $appointment->loadMissing(['doctor', 'specialty', 'sala', 'patient', 'healthInsurance']);
        $fechaEmision = $fechaEmision ?? Carbon::now();

        $pdf = Pdf::loadView('certificates.certificates', [
            'appointment'   => $appointment,
            'fechaEmision'  => $fechaEmision,
            'observaciones' => $observaciones,
        ])->setPaper('a4', 'portrait');

        $fileName = "certificados/certificado_turno_{$appointment->id}_" . time() . ".pdf";
        Documents::disk()->put($fileName, $pdf->output());

        $certificate = Certificate::firstOrNew(['appointment_id' => $appointment->id]);
        $certificate->certificate_url = $fileName;
        $certificate->issue_date = $fechaEmision;
        $certificate->save();

        return $fileName;
    }

    /**
     * Generar PDF, guardar en storage, actualizar turno y enviar por email al paciente.
     */
    public function generateAndSend(Appointment $appointment, ?string $observaciones = null): string
    {
        $fechaEmision = Carbon::now();
        $token = Str::random(64);

        $fileName = $this->generate($appointment, $observaciones, $fechaEmision);

        $appointment->update([
            'certificado_url'     => $fileName,
            'fecha_emision'       => $fechaEmision,
            'certificado_token'   => $token,
            'observaciones_cert'  => $observaciones,
        ]);

        // 4. Construir URL pública de visualización
        $viewUrl = url("/api/certificado/{$appointment->id}/{$token}");

        // 5. Enviar email al paciente con el PDF adjunto y link de visualización
        app(AppointmentNotificationService::class)->notifyCertificate($appointment, $fileName, $viewUrl);

        return $fileName;
    }

    /**
     * Renderizar la vista HTML del certificado para mostrar en el navegador.
     */
    public function renderHtml(Appointment $appointment): string
    {
        $appointment->loadMissing(['doctor', 'specialty', 'sala', 'patient', 'healthInsurance']);

        return view('certificates.certificates', [
            'appointment'   => $appointment,
            'fechaEmision'  => $appointment->fecha_emision ?? now(),
            'observaciones' => $appointment->observaciones_cert,
        ])->render();
    }
}
