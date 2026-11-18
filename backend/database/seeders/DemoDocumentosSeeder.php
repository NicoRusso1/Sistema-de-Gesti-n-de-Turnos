<?php

namespace Database\Seeders;

use App\Enums\DocumentIssueStatus;
use App\Enums\PaymentStatusName;
use App\Models\Appointment;
use App\Models\Certificate;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\CertificateService;
use App\Services\InvoiceService;
use App\Services\PrescriptionService;
use App\Support\Documents;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoDocumentosSeeder extends Seeder
{
    private const OBSERVACIONES = [
        'Paciente en buen estado general. Control en 6 meses.',
        'Se indica reposo relativo por 48 horas.',
        'Continuar con el tratamiento indicado y repetir estudios en 30 días.',
    ];

    private const RECETAS = [
        'Cardiología' => [
            "Enalapril 10 mg: 1 comprimido cada 12 horas, uso continuo.\nAtorvastatina 20 mg: 1 comprimido por la noche.\nControl de presión arterial semanal.",
            "Aspirina 100 mg: 1 comprimido por día después del almuerzo.\nBisoprolol 5 mg: 1 comprimido por la mañana.\nElectrocardiograma de control en 3 meses.",
        ],
        'Clínica Médica' => [
            "Ibuprofeno 400 mg: 1 comprimido cada 8 horas con las comidas, por 5 días.\nHidratación abundante y reposo.",
            "Omeprazol 20 mg: 1 cápsula en ayunas durante 30 días.\nEvitar comidas abundantes y picantes.",
            "Amoxicilina 875 mg + ácido clavulánico 125 mg: 1 comprimido cada 12 horas por 7 días.\nParacetamol 500 mg si presenta fiebre.",
        ],
        'Pediatria' => [
            "Paracetamol en gotas: 15 gotas cada 6 horas si presenta fiebre.\nControl pediátrico en 7 días.",
            "Amoxicilina 250 mg/5 ml: 5 ml cada 8 horas por 7 días.\nAbundante líquido y reposo.",
        ],
        'Ginecología' => [
            "Ácido fólico 5 mg: 1 comprimido por día.\nPapanicolaou y colposcopía de control en 12 meses.",
            "Hierro polimaltosado 100 mg: 1 comprimido por día con jugo de naranja durante 60 días.\nAnálisis de sangre de control.",
        ],
    ];

    public function run(): void
    {
        $pacientesIds = Patient::whereIn('email', DemoTurnosSeeder::PACIENTES)->pluck('id');

        $turnos = Appointment::with(['patient', 'doctor', 'specialty', 'sala', 'healthInsurance', 'payments.status', 'payments.method'])
            ->whereIn('patient_id', $pacientesIds)
            ->whereIn('status', ['abonado', 'completado'])
            ->orderBy('scheduled_at')
            ->get();

        $recetasEmitidas = 0;

        foreach ($turnos as $turno) {
            $pago = $turno->payments->first(
                fn ($p) => $p->status->name === PaymentStatusName::Approved->value
            );

            if (!$pago) {
                continue;
            }

            $this->emitirComprobantes($turno, $pago);

            if ($turno->status !== 'completado') {
                continue;
            }

            $this->emitirCertificadoDelMedico($turno);

            $esDeMaria = $turno->patient->email === DemoTurnosSeeder::PACIENTES['maria'];

            if ($esDeMaria || $recetasEmitidas++ % 2 === 0) {
                $this->emitirReceta($turno, $recetasEmitidas);
            }
        }
    }

    private function emitirComprobantes(Appointment $turno, $pago): void
    {
        $certificado = Certificate::where('appointment_id', $turno->id)->first();

        if ($certificado
            && $certificado->estado_emision === DocumentIssueStatus::Issued
            && Documents::exists($certificado->certificate_url)
            && Documents::exists($certificado->invoice_url)) {
            return;
        }

        $fechaEmision = $pago->payment_date->copy()->addMinute();

        app(CertificateService::class)->generate($turno, null, $fechaEmision);
        app(InvoiceService::class)->generate($turno, $pago);

        $certificado = Certificate::where('appointment_id', $turno->id)->firstOrFail();

        $certificado->forceFill([
            'estado_emision' => DocumentIssueStatus::Issued,
            'error_emision' => null,
            'ultimo_intento_at' => $fechaEmision,
            'issue_date' => $fechaEmision,
        ])->save();

        DB::table('certificados')->where('id', $certificado->id)->update([
            'created_at' => $fechaEmision,
            'updated_at' => $fechaEmision,
        ]);
    }

    private function emitirCertificadoDelMedico(Appointment $turno): void
    {
        if ($turno->certificado_token) {
            return;
        }

        $certificado = Certificate::where('appointment_id', $turno->id)->firstOrFail();

        $turno->forceFill([
            'certificado_url' => $certificado->certificate_url,
            'fecha_emision' => $certificado->issue_date,
            'certificado_token' => Str::random(64),
            'observaciones_cert' => self::OBSERVACIONES[$turno->id % count(self::OBSERVACIONES)],
        ])->saveQuietly();
    }

    private function emitirReceta(Appointment $turno, int $secuencia): void
    {
        if (Prescription::where('appointment_id', $turno->id)->exists()) {
            return;
        }

        $nombre = $turno->specialty->name;
        $opciones = self::RECETAS[$nombre] ?? self::RECETAS['Clínica Médica'];
        $contenido = $opciones[($turno->id + $secuencia) % count($opciones)];

        $receta = app(PrescriptionService::class)->issue($turno, $turno->doctor, $contenido);

        if ($receta->estado_emision !== DocumentIssueStatus::Issued || !Documents::exists($receta->file_url)) {
            $receta->delete();

            throw new RuntimeException("No se pudo generar el PDF de la receta del turno #{$turno->id}.");
        }

        $emision = $turno->scheduled_at->copy()->addMinutes(35);

        DB::table('prescriptions')->where('id', $receta->id)->update([
            'issue_date' => $emision,
            'created_at' => $emision,
            'updated_at' => $emision,
        ]);
    }
}
