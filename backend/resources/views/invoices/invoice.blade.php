<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura de Pago</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #222; margin: 30px; font-size: 14px; }
        .header { text-align: center; border-bottom: 2px solid #1B4B5A; padding-bottom: 15px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #1B4B5A; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 4px 0 0 0; color: #64748b; font-size: 13px; }
        .title { text-align: center; margin: 30px 0; font-size: 20px; font-weight: bold; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        td, th { padding: 8px; font-size: 13px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; }
        .right { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        .footer { position: fixed; bottom: 20px; left: 30px; right: 30px; font-size: 11px; text-align: center; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SIGT - Sistema Integral de Gestión de Turnos</h1>
        <p>Centro de Atención Médica y Consultorios de Especialidades</p>
    </div>

    <div class="title">FACTURA DE PAGO N° {{ str_pad($payment->id, 8, '0', STR_PAD_LEFT) }}</div>

    <table>
        <tr>
            <td><strong>Paciente:</strong> {{ $appointment->patient?->first_name }} {{ $appointment->patient?->last_name }}</td>
            <td><strong>Fecha de emisión:</strong> {{ $issueDate->format('d/m/Y H:i') }} hs</td>
        </tr>
        <tr>
            <td><strong>Obra Social:</strong> {{ $appointment->healthInsurance?->name ?? 'Particular' }}</td>
            <td><strong>N° de Afiliado:</strong> {{ $appointment->affiliate_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Método de pago:</strong> {{ $payment->method?->name ?? 'N/A' }}</td>
            <td><strong>Fecha de pago:</strong> {{ ($payment->payment_date ?? $issueDate)->format('d/m/Y H:i') }} hs</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>Concepto</th>
            <th class="right">Importe</th>
        </tr>
        <tr>
            <td>
                Consulta de {{ $appointment->specialty?->name ?? 'Medicina General' }}
                con Dr/a. {{ $appointment->doctor?->first_name }} {{ $appointment->doctor?->last_name }}
                el {{ $appointment->scheduled_at->format('d/m/Y') }} a las {{ $appointment->scheduled_at->format('H:i') }} hs
                (Turno #{{ $appointment->id }})
            </td>
            <td class="right">$ {{ number_format((float) $appointment->monto_base, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Descuento por obra social</td>
            <td class="right">- $ {{ number_format((float) $appointment->monto_descuento, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="total">Total abonado</td>
            <td class="right total">$ {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Comprobante de pago emitido por la plataforma oficial SIGT. Referencia: #FAC-{{ $appointment->id }}-{{ $payment->id }}.
    </div>
</body>
</html>
