<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; color: #333; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background-color: #1976d2; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .content { padding: 24px; }
        .section-title { font-size: 16px; font-weight: bold; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; margin-top: 20px; margin-bottom: 12px; color: #1976d2; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .item-label { font-weight: bold; color: #555; }
        .total-box { background-color: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px; padding: 12px; margin-top: 16px; text-align: right; }
        .total-label { font-size: 14px; color: #2e7d32; font-weight: bold; }
        .total-amount { font-size: 20px; color: #1b5e20; font-weight: bold; }
        .footer { background: #fafafa; padding: 16px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eeeeee; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>¡Tu Turno está Confirmado!</h1>
            <p style="margin: 5px 0 0 0;">Hemos acreditado tu pago exitosamente.</p>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $appointment->patient?->first_name }} {{ $appointment->patient?->last_name }}</strong>,</p>
            <p>Te confirmamos que tu reserva médica ha sido abonada y confirmada.</p>

            <div class="section-title">Detalles del Turno</div>
            <div class="item-row">
                <span class="item-label">Fecha y Hora:</span>
                <span>{{ $appointment->scheduled_at->format('d/m/Y H:i') }} hs</span>
            </div>
            <div class="item-row">
                <span class="item-label">Profesional:</span>
                <span>Dr/a. {{ $appointment->doctor?->first_name }} {{ $appointment->doctor?->last_name }}</span>
            </div>
            <div class="item-row">
                <span class="item-label">Especialidad:</span>
                <span>{{ $appointment->specialty?->name ?? 'Consulta General' }}</span>
            </div>
            @if($appointment->sala)
            <div class="item-row">
                <span class="item-label">Ubicación / Sala:</span>
                <span>Sala {{ $appointment->sala->numero }} (Piso {{ $appointment->sala->piso }})</span>
            </div>
            @endif

            <div class="section-title">Cobertura Médica</div>
            <div class="item-row">
                <span class="item-label">Obra Social:</span>
                <span>{{ $appointment->healthInsurance?->name ?? 'Particular' }}</span>
            </div>
            @if($appointment->affiliate_number)
            <div class="item-row">
                <span class="item-label">N° de Afiliado:</span>
                <span>{{ $appointment->affiliate_number }}</span>
            </div>
            @endif

            <div class="section-title">Comprobante de Pago</div>
            <div class="item-row">
                <span class="item-label">Transacción MercadoPago:</span>
                <span>#{{ $payment->provider_payment_id ?? $payment->id }}</span>
            </div>
            <div class="item-row">
                <span class="item-label">Monto Base:</span>
                <span>${{ number_format($appointment->monto_base, 2, ',', '.') }}</span>
            </div>
            @if($appointment->monto_descuento > 0)
            <div class="item-row">
                <span class="item-label">Descuento Obra Social:</span>
                <span>-${{ number_format($appointment->monto_descuento, 2, ',', '.') }}</span>
            </div>
            @endif
            <div class="total-box">
                <div class="total-label">Total Abonado:</div>
                <div class="total-amount">${{ number_format($payment->amount, 2, ',', '.') }}</div>
            </div>
        </div>
        <div class="footer">
            <p>Si necesitás reprogramar o cancelar tu turno, podés hacerlo ingresando a la plataforma con al menos 24 hs de anticipación.</p>
        </div>
    </div>
</body>
</html>