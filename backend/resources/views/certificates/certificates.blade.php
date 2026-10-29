<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado Médico Digital</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #222; margin: 30px; font-size: 14px; }
        .header { text-align: center; border-bottom: 2px solid #1B4B5A; padding-bottom: 15px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #1B4B5A; font-size: 24px; text-transform: uppercase; }
        .header p { margin: 4px 0 0 0; color: #64748b; font-size: 13px; }
        .title-cert { text-align: center; margin: 35px 0; font-size: 20px; font-weight: bold; letter-spacing: 1px; color: #0f172a; }
        .body-text { line-height: 1.8; text-align: justify; margin-bottom: 40px; font-size: 15px; }
        .highlight { font-weight: bold; color: #0f172a; }
        .details-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; margin-bottom: 50px; }
        .details-box table { width: 100%; border-collapse: collapse; }
        .details-box td { padding: 6px; font-size: 13px; }
        .signature-section { margin-top: 60px; text-align: center; width: 250px; float: right; }
        .signature-firma { font-family: cursive; font-style: italic; font-size: 32px; color: #1a1a6e; letter-spacing: -2px; transform: skewX(-12deg); display: inline-block; line-height: 1; margin-bottom: 6px; }
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

    <div class="title-cert">CERTIFICADO DE ATENCIÓN MÉDICA</div>

    <div class="body-text">
        Por medio de la presente, se certifica que el/la paciente <span class="highlight">{{ $appointment->patient?->first_name }} {{ $appointment->patient?->last_name }}</span>
        concurrió a este centro de atención médica el día <span class="highlight">{{ $appointment->scheduled_at->format('d/m/Y') }}</span> a las <span class="highlight">{{ $appointment->scheduled_at->format('H:i') }} hs</span>, 
        habiendo sido atendido/a por consulta en el área de <span class="highlight">{{ $appointment->specialty?->name ?? 'Medicina General' }}</span>.
    </div>

    <div class="details-box">
        <table>
            <tr>
                <td><strong>Turno N°:</strong> #{{ $appointment->id }}</td>
                <td><strong>Fecha de Emisión:</strong> {{ $fechaEmision->format('d/m/Y H:i') }} hs</td>
            </tr>
            <tr>
                <td><strong>Obra Social:</strong> {{ $appointment->healthInsurance?->name ?? 'Particular' }}</td>
                <td><strong>N° de Afiliado:</strong> {{ $appointment->affiliate_number ?? 'N/A' }}</td>
            </tr>
            @if($appointment->sala)
            <tr>
                <td colspan="2"><strong>Ubicación:</strong> Sala {{ $appointment->sala->numero }} - Piso {{ $appointment->sala->piso }}</td>
            </tr>
            @endif
            @if($observaciones)
            <tr>
                <td colspan="2" style="padding-top: 10px;"><strong>Observaciones / Diagnóstico:</strong> {{ $observaciones }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div class="signature-section">
    <div class="signature-firma">
        {{ collect(explode(' ', trim($appointment->doctor?->first_name . ' ' . $appointment->doctor?->last_name)))->map(fn($p) => substr($p,0,1))->implode('') }}~
    </div>
    <div class="signature-line">
        <div class="name">Dr/a. {{ $appointment->doctor?->first_name }} {{ $appointment->doctor?->last_name }}</div>
        <div class="spec">{{ $appointment->specialty?->name ?? 'Profesional Médico' }}</div>
        <div class="spec">Firma y Sello Digital Autorizado</div>
    </div>
    </div>

    <div class="footer">
        Documento médico digital emitido por la plataforma oficial SIGT. Código de verificación: #CERT-{{ $appointment->id }}-{{ $appointment->scheduled_at->timestamp }}.
    </div>
</body>
</html>