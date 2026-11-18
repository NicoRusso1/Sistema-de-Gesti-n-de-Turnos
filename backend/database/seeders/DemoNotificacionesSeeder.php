<?php

namespace Database\Seeders;

use App\Enums\NotificationDeliveryStatus;
use App\Enums\PaymentStatusName;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoNotificacionesSeeder extends Seeder
{
    private const ERRORES_DE_ENVIO = [
        'Connection could not be established with host "smtp.hospital.test:587": Connection timed out',
        'Expected response code "250" but got code "550", with message "550 5.1.1 The email account that you tried to reach does not exist."',
        'Expected response code "354" but got code "421", with message "421 4.7.0 Try again later, closing connection."',
    ];

    public function run(): void
    {
        $pacientesIds = Patient::whereIn('email', DemoTurnosSeeder::PACIENTES)->pluck('id');

        $turnos = Appointment::with(['doctor', 'specialty', 'sala', 'payments.status'])
            ->whereIn('patient_id', $pacientesIds)
            ->orderBy('scheduled_at')
            ->get();

        DB::transaction(function () use ($turnos) {
            $posicionRecordatorio = 0;
            $recordatoriosFuturos = 0;

            foreach ($turnos as $turno) {
                if ($turno->status === 'cancelado') {
                    $this->sembrarCancelacion($turno);

                    continue;
                }

                $pagoAprobado = $turno->payments->first(
                    fn ($pago) => $pago->status->name === PaymentStatusName::Approved->value
                );

                if ($pagoAprobado) {
                    $this->sembrarConfirmacion($turno, $pagoAprobado->payment_date);
                }

                if ($turno->status === 'completado' && $turno->id % 2 === 0) {
                    $this->sembrarCertificado($turno, $turno->id % 12 === 0);
                }

                $dias = today()->diffInDays($turno->scheduled_at->copy()->startOfDay(), false);

                if ($dias >= -6 && $dias <= 2) {
                    $this->sembrarRecordatorio($turno, $posicionRecordatorio++, $turno->scheduled_at->isFuture() ? $recordatoriosFuturos++ : null);
                }
            }
        });
    }

    private function sembrarConfirmacion(Appointment $turno, Carbon $fechaPago): void
    {
        $this->registrar($turno, $turno->patient_id, 'confirmacion', [
            'titulo' => 'Turno confirmado',
            'mensaje' => "Se acreditó el pago de tu turno del {$this->fecha($turno)} a las {$this->hora($turno)} hs. Te enviamos la factura y el certificado por email.",
        ], NotificationDeliveryStatus::Sent, $fechaPago->copy()->addMinute());
    }

    private function sembrarCertificado(Appointment $turno, bool $fallida): void
    {
        $momento = $turno->scheduled_at->copy()->addMinutes(40);

        $this->registrar($turno, $turno->patient_id, 'certificado', [
            'titulo' => 'Certificado digital disponible',
            'mensaje' => "Ya podés consultar el certificado de tu turno #{$turno->id}.",
        ], $fallida ? NotificationDeliveryStatus::Failed : NotificationDeliveryStatus::Sent, $momento);
    }

    private function sembrarRecordatorio(Appointment $turno, int $posicion, ?int $posicionFutura): void
    {
        $momento = $turno->scheduled_at->copy()->subDay();

        if ($momento->gt(now())) {
            $momento = now()->subMinutes(30 + $posicion * 7);
        }

        $estado = match (true) {
            $posicionFutura === 0 => NotificationDeliveryStatus::Pending,
            $posicion % 4 === 1 => NotificationDeliveryStatus::Failed,
            default => NotificationDeliveryStatus::Sent,
        };

        $doctor = $this->nombreDelMedico($turno);
        $especialidad = $turno->specialty?->name ?? 'Consulta General';
        $sala = $turno->sala ? " en Sala {$turno->sala->numero} (Piso {$turno->sala->piso})" : '';
        $estadoTurno = strtoupper($turno->status);

        $this->registrar($turno, $turno->patient_id, 'recordatorio', [
            'titulo' => "Tu próximo turno es el {$this->fecha($turno)} a las {$this->hora($turno)} hs",
            'mensaje' => "Recordá que tu próximo turno está agendado para el día {$this->fecha($turno)} a las {$this->hora($turno)} hs con {$doctor} ({$especialidad}){$sala}. Estado del turno: {$estadoTurno}.",
        ], $estado, $momento, $posicion);
    }

    private function sembrarCancelacion(Appointment $turno): void
    {
        $momento = $turno->updated_at->copy();
        $motivo = $turno->cancellation_reason ? " Motivo: {$turno->cancellation_reason}." : '';

        $this->registrar($turno, $turno->patient_id, 'cancelacion', [
            'titulo' => 'Turno cancelado',
            'mensaje' => "Tu turno del {$this->fecha($turno)} {$this->hora($turno)} con {$this->nombreDelMedico($turno)} ha sido cancelado.{$motivo}",
        ], NotificationDeliveryStatus::Sent, $momento);

        $this->registrar($turno, $turno->doctor_id, 'cancelacion', [
            'titulo' => 'Turno cancelado en tu agenda',
            'mensaje' => "El turno del {$this->fecha($turno)} {$this->hora($turno)} ha sido cancelado y el horario quedó disponible.{$motivo}",
        ], NotificationDeliveryStatus::Sent, $momento);
    }

    private function registrar(
        Appointment $turno,
        int $destinatarioId,
        string $tipo,
        array $contenido,
        NotificationDeliveryStatus $estado,
        Carbon $momento,
        int $posicionDeError = 0
    ): void {
        $existe = Notification::where('patient_id', $destinatarioId)
            ->where('appointment_id', $turno->id)
            ->where('tipo', $tipo)
            ->exists();

        if ($existe) {
            return;
        }

        $notificacion = new Notification($contenido + [
            'patient_id' => $destinatarioId,
            'appointment_id' => $turno->id,
            'tipo' => $tipo,
            'leido' => $momento->lt(now()->subDays(3)),
            'estado_envio' => $estado,
            'error_envio' => $estado === NotificationDeliveryStatus::Failed
                ? self::ERRORES_DE_ENVIO[($turno->id + $posicionDeError) % count(self::ERRORES_DE_ENVIO)]
                : null,
            'enviada_at' => $estado === NotificationDeliveryStatus::Sent ? $momento : null,
        ]);

        $notificacion->forceFill(['created_at' => $momento, 'updated_at' => $momento])->save();
    }

    private function nombreDelMedico(Appointment $turno): string
    {
        return "Dr/a. {$turno->doctor->first_name} {$turno->doctor->last_name}";
    }

    private function fecha(Appointment $turno): string
    {
        return $turno->scheduled_at->format('d/m/Y');
    }

    private function hora(Appointment $turno): string
    {
        return $turno->scheduled_at->format('H:i');
    }
}
