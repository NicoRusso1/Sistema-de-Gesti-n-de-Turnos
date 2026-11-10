<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Receta Digital</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #222; margin: 30px; font-size: 14px; }
        .header { text-align: center; border-bottom: 2px solid #1B4B5A; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { margin: 0; color: #1B4B5A; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 4px 0 0 0; color: #64748b; font-size: 13px; }
        .title { text-align: center; margin: 25px 0; font-size: 20px; font-weight: bold; letter-spacing: 1px; color: #0f172a; }
        .details-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; margin-bottom: 25px; }
        .details-box table { width: 100%; border-collapse: collapse; }
        .details-box td { padding: 6px; font-size: 13px; }
        .rp { font-size: 22px; font-weight: bold; color: #1B4B5A; margin-bottom: 8px; }
        .content { white-space: pre-line; line-height: 1.7; font-size: 15px; min-height: 220px; border-left: 3px solid #ED7D5C; padding-left: 14px; }
        .signature-section { margin-top: 60px; text-align: center; width: 250px; float: right; }
        .signature-firma { font-family: cursive; font-style: italic; font-size: 32px; color: #1a1a6e; letter-spacing: -2px; display: inline-block; line-height: 1; margin-bottom: 6px; }
        .signature-line { border-top: 1px solid #333; padding-top: 6px; margin-top: 4px; }
        .signature-section .name { font-weight: bold; font-size: 13px; }
        .signature-section .spec { font-size: 12px; color: #555; }
        .footer { position: fixed; bottom: 20px; left: 30px; right: 30px; font-size: 11px; text-align: center; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SIGT - Sistema Integral de Gestión de Turnos</h1>
        <p>Centro de Atención Médica y Consultorios de Especialidades</p>
    </div>

    <div class="title">RECETA DIGITAL N° {{ str_pad($prescription->id, 8, '0', STR_PAD_LEFT) }}</div>

    <div class="details-box">
        <table>
            <tr>
                <td><strong>Paciente:</strong> {{ $prescription->patient?->first_name }} {{ $prescription->patient?->last_name }}</td>
                <td><strong>Fecha de emisión:</strong> {{ $issueDate->format('d/m/Y H:i') }} hs</td>
            </tr>
            <tr>
                <td><strong>Obra Social:</strong> {{ $appointment->healthInsurance?->name ?? 'Particular' }}</td>
                <td><strong>N° de Afiliado:</strong> {{ $appointment->affiliate_number ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td><strong>Turno N°:</strong> #{{ $appointment->id }} ({{ $appointment->scheduled_at->format('d/m/Y H:i') }} hs)</td>
                <td><strong>Especialidad:</strong> {{ $appointment->specialty?->name ?? 'Medicina General' }}</td>
            </tr>
        </table>
    </div>

    <div class="rp">Rp/</div>
    <div class="content">{{ $prescription->content }}</div>

    <div class="signature-section">
        <div class="signature-firma">{{ $prescription->doctor?->last_name }}</div>
        <div class="signature-line">
            <div class="name">Dr/a. {{ $prescription->doctor?->first_name }} {{ $prescription->doctor?->last_name }}</div>
            <div class="spec">{{ $appointment->specialty?->name ?? 'Medicina General' }}</div>
        </div>
    </div>

    <div class="footer">
        Receta digital emitida por la plataforma SIGT. Referencia: #REC-{{ $appointment->id }}-{{ $prescription->id }}.
    </div>
</body>
</html>
