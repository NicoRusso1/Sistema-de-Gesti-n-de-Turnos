<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodName;
use App\Enums\PaymentStatusName;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoPagosSeeder extends Seeder
{
    public function run(): void
    {
        $pacientesIds = Patient::whereIn('email', DemoTurnosSeeder::PACIENTES)->pluck('id');

        $turnos = Appointment::whereIn('patient_id', $pacientesIds)
            ->whereIn('status', ['abonado', 'completado', 'reservado'])
            ->orderBy('scheduled_at')
            ->get();

        $estados = PaymentStatus::pluck('id', 'name');
        $metodos = PaymentMethod::pluck('id', 'name');
        $metodosAprobados = [
            PaymentMethodName::Credit->value,
            PaymentMethodName::Debit->value,
            PaymentMethodName::Transfer->value,
        ];

        DB::transaction(function () use ($turnos, $estados, $metodos, $metodosAprobados) {
            $reservados = 0;

            foreach ($turnos as $turno) {
                if ($turno->status === 'reservado') {
                    $this->sembrarPagoDeReservado($turno, $reservados++, $estados, $metodos);

                    continue;
                }

                $this->sembrarPagoAprobado($turno, $metodosAprobados[$turno->id % 3], $estados, $metodos);
            }
        });
    }

    private function sembrarPagoAprobado(Appointment $turno, string $metodo, $estados, $metodos): void
    {
        $fechaPago = $this->fechaDePago($turno);

        $this->guardar(
            ['provider_payment_id' => $this->idDelProveedor($turno, 'APR')],
            [
                'appointment_id' => $turno->id,
                'payment_method_id' => $metodos[$metodo],
                'payment_status_id' => $estados[PaymentStatusName::Approved->value],
                'amount' => $turno->monto_final,
                'payment_date' => $fechaPago,
                'provider_preference_id' => $this->preferencia($turno),
            ],
            $fechaPago
        );
    }

    private function sembrarPagoDeReservado(Appointment $turno, int $posicion, $estados, $metodos): void
    {
        $intento = $turno->created_at->copy()->addHours(2 + $turno->id % 6);
        if ($intento->gt(now())) {
            $intento = now()->subMinutes(20 + $turno->id % 40);
        }

        $intento = $intento->lt($turno->created_at) ? $turno->created_at->copy()->addMinutes(5) : $intento;

        if ($posicion % 8 === 2 || $posicion % 8 === 5) {
            $metodo = $posicion % 2 === 0 ? PaymentMethodName::Credit : PaymentMethodName::Debit;

            $this->guardar(
                ['provider_payment_id' => $this->idDelProveedor($turno, 'RECH')],
                [
                    'appointment_id' => $turno->id,
                    'payment_method_id' => $metodos[$metodo->value],
                    'payment_status_id' => $estados[PaymentStatusName::Rejected->value],
                    'amount' => $turno->monto_final,
                    'payment_date' => $intento,
                    'provider_preference_id' => $this->preferencia($turno),
                ],
                $intento
            );

            return;
        }

        $this->guardar(
            [
                'appointment_id' => $turno->id,
                'payment_status_id' => $estados[PaymentStatusName::Pending->value],
                'provider_payment_id' => null,
            ],
            [
                'payment_method_id' => null,
                'amount' => $turno->monto_final,
                'payment_date' => null,
                'provider_preference_id' => $this->preferencia($turno),
            ],
            $intento
        );
    }

    private function guardar(array $clave, array $datos, Carbon $momento): void
    {
        $pago = Payment::firstOrNew($clave);

        if ($pago->exists) {
            return;
        }

        $pago->fill($datos);
        $pago->forceFill(['created_at' => $momento, 'updated_at' => $momento])->save();
    }

    private function fechaDePago(Appointment $turno): Carbon
    {
        $fecha = $turno->created_at->copy()->addHours(3 + $turno->id % 20);
        $limite = $turno->scheduled_at->copy()->subHour();

        if ($fecha->gt($limite)) {
            $fecha = $limite;
        }

        if ($fecha->gt(now())) {
            $fecha = now()->subMinutes(10 + $turno->id % 50);
        }

        $minimo = $turno->created_at->copy()->addMinutes(5);

        return $fecha->lt($minimo) ? $minimo : $fecha;
    }

    private function idDelProveedor(Appointment $turno, string $tipo): string
    {
        return sprintf('DEMO-MP-%s-%05d', $tipo, $turno->id);
    }

    private function preferencia(Appointment $turno): string
    {
        return sprintf('DEMO-PREF-%05d', $turno->id);
    }
}
