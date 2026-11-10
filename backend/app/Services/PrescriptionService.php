<?php

namespace App\Services;

use App\Enums\DocumentIssueStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Support\Documents;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

class PrescriptionService
{
    /**
     * Guarda la receta y genera el PDF. El contenido queda guardado aunque
     * falle la generación, para poder reintentar sin volver a cargarlo.
     */
    public function issue(Appointment $appointment, Patient $doctor, string $content): Prescription
    {
        $prescription = Prescription::create([
            'appointment_id' => $appointment->id,
            'doctor_id'      => $doctor->id,
            'patient_id'     => $appointment->patient_id,
            'content'        => $content,
            'estado_emision' => DocumentIssueStatus::Pending,
        ]);

        return $this->generate($prescription);
    }

    public function generate(Prescription $prescription): Prescription
    {
        $prescription->loadMissing(['appointment.specialty', 'appointment.healthInsurance', 'doctor', 'patient']);
        $issueDate = now();

        try {
            $pdf = Pdf::loadView('prescriptions.prescription', [
                'prescription' => $prescription,
                'appointment'  => $prescription->appointment,
                'issueDate'    => $issueDate,
            ])->setPaper('a4', 'portrait');

            $fileName = "recetas/receta_{$prescription->id}_" . time() . '.pdf';

            if (!Documents::disk()->put($fileName, $pdf->output())) {
                throw new \RuntimeException('No se pudo guardar el archivo de la receta');
            }

            $prescription->update([
                'file_url'       => $fileName,
                'issue_date'     => $issueDate,
                'estado_emision' => DocumentIssueStatus::Issued,
                'error_emision'  => null,
            ]);
        } catch (Throwable $e) {
            logger()->error("Error generando la receta #{$prescription->id}: " . $e->getMessage());

            $prescription->update([
                'estado_emision' => DocumentIssueStatus::Failed,
                'error_emision'  => $e->getMessage(),
            ]);
        }

        return $prescription;
    }
}
