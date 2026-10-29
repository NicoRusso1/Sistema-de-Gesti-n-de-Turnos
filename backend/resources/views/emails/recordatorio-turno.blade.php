<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f8; margin: 0; padding: 20px; color: #333; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background-color: #1B4B5A; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 22px; }
        .content { padding: 24px; }
        .section-title { font-size: 15px; font-weight: bold; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px; margin-top: 18px; margin-bottom: 12px; color: #1B4B5A; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .item-label { font-weight: bold; color: #555; }
        .alert-box { background-color: #eff6ff; border-left: 4px solid #1976d2; padding: 12px; margin-top: 16px; font-size: 13px; color: #1e3a8a; border-radius: 4px; }
        .footer { background: #fafafa; padding: 16px; text-align: center; font-size: 12px; color: #777; border-top: 1px solid #eeeeee; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>⏰ Recordatorio de Turno Médico</h1>
            <p style="margin: 5px 0 0 0;">Te recordamos tu cita programada para mañana</p>
        </div>
        <div class="content">
            <p>Hola <strong>{{ $appointment->patient?->first_name }} {{ $appointment->patient?->last_name }}</strong>,</p>
            <p>Este es un recordatorio de que tenés un turno médico programado en nuestro centro de atención:</p>

            <div class="section-title">Detalles de la Cita</div>
            <div class="item-row">
                <span class="item-label">Fecha y Hora:</span>
                <span><strong>{{ $appointment->scheduled_at->format('d/m/Y H:i') }} hs</strong></span>
            </div>
            <div class="item-row">
                <span class="item-label">Profesional:</span>
                <span>Dr/a. {{ $appointment->doctor?->first_name }} {{ $appointment->doctor?->last_name }}</span>
            </div>
            <div class="item-row">
                <span class="item-label">Especialidad:</span>
                <span>{{ $appointment->specialty?->name ?? 'Consulta Médica' }}</span>
            </div>
            @if($appointment->sala)
            <div class="item-row">
                <span class="item-label">Ubicación / Sala:</span>
                <span>Sala {{ $appointment->sala->numero }} (Piso {{ $appointment->sala->piso }})</span>
            </div>
            @endif
            <div class="item-row">
                <span class="item-label">Estado:</span>
                <span>{{ strtoupper($appointment->status) }}</span>
            </div>

            <div class="alert-box">
                <strong>Recomendación:</strong> Por favor, presentate con 10 minutos de anticipación en mesa de entrada con tu DNI y carnet de obra social si corresponde.
            </div>
        </div>
        <div class="footer">
            <p>Si necesitás reprogramar o cancelar tu turno, recordá hacerlo a través del sistema con anticipación.</p>
        </div>
    </div>
</body>
</html>