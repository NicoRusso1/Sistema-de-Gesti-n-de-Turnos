<?php

namespace App\Services;

use App\Mail\CertificadoDigitalMail;
use App\Models\Appointment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    /**
     * Generar PDF, guardar en storage, actualizar turno y enviar por email al paciente.
     */
    public function generateAndSend(Appointment $appointment, ?string $observaciones = null): string
    {
        $appointment->loadMissing(['doctor', 'specialty', 'sala', 'patient', 'healthInsurance']);
        $fechaEmision = Carbon::now();

        // Generar token único para ver el certificado desde el navegador
        $token = Str::random(64);

        // 1. Renderizar PDF con DomPDF
        $pdf = Pdf::loadView('certificates.certificates', [
            'appointment'   => $appointment,
            'fechaEmision'  => $fechaEmision,
            'observaciones' => $observaciones,
        ])->setPaper('a4', 'portrait');

        // 2. Guardar en storage/app/public/certificados/
        $fileName = "certificados/certificado_turno_{$appointment->id}_" . time() . ".pdf";
        Storage::disk('public')->put($fileName, $pdf->output());

        // 3. Actualizar registro del turno
        $appointment->update([
            'certificado_url'     => $fileName,
            'fecha_emision'       => $fechaEmision,
            'certificado_token'   => $token,
            'observaciones_cert'  => $observaciones,
        ]);

        // 4. Construir URL pública de visualización
        $viewUrl = url("/api/certificado/{$appointment->id}/{$token}");

        // 5. Enviar email al paciente con el PDF adjunto y link de visualización
        if ($email = $appointment->patient?->email) {
            try {
                Mail::to($email)->send(new CertificadoDigitalMail($appointment, $fileName, $viewUrl));
            } catch (\Throwable $e) {
                logger()->error("Error enviando certificado por email turno #{$appointment->id}: " . $e->getMessage());
            }
        }

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
