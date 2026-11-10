<?php

namespace App\Commands;

use App\Enums\PaymentStatusName;
use App\Models\Payment;
use App\Services\PaymentDocumentsService;
use Illuminate\Console\Command;

class RetryPaymentDocuments extends Command
{
    protected $signature = 'facturas:reintentar
        {--max-intentos= : Fallos tolerados antes de dejarlo para reintento manual}
        {--dias= : Solo revisa pagos aprobados en los últimos N días}';

    protected $description = 'Reintenta la emisión y el envío por email de la factura de los pagos aprobados que fallaron';

    public function handle(PaymentDocumentsService $documents): int
    {
        $maxIntentos = (int) ($this->option('max-intentos') ?: config('notifications.invoice_retry.max_attempts', 5));
        $dias = (int) ($this->option('dias') ?: config('notifications.invoice_retry.days', 7));

        $pagos = Payment::with('appointment.patient')
            ->whereHas('status', fn ($q) => $q->where('name', PaymentStatusName::Approved->value))
            ->where('payment_date', '>=', now()->subDays($dias))
            ->get()
            ->reject(fn (Payment $pago) => $documents->isComplete($pago));

        if ($pagos->isEmpty()) {
            $this->info('No hay facturas pendientes de reintento.');
            return Command::SUCCESS;
        }

        $resueltos = 0;

        foreach ($pagos as $pago) {
            $turno = $pago->appointment_id;

            if ($documents->failedAttempts($pago) >= $maxIntentos) {
                $this->warn("Turno #{$turno}: superó los {$maxIntentos} intentos, queda para reintento manual.");
                continue;
            }

            $documents->process($pago);

            if ($documents->isComplete($pago)) {
                $this->line("Turno #{$turno}: factura emitida y enviada.");
                $resueltos++;
            } else {
                $this->error("Turno #{$turno}: volvió a fallar.");
            }
        }

        $this->info("Proceso finalizado. Facturas resueltas: {$resueltos}");
        return Command::SUCCESS;
    }
}
