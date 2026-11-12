<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Métricas Globales - SIGT</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; line-height: 1.4; margin: 20px; }
        .header { border-bottom: 2px solid #1B4B5A; padding-bottom: 15px; margin-bottom: 20px; }
        .logo-title { font-size: 20px; font-weight: bold; color: #1B4B5A; }
        .periodo { font-size: 12px; color: #64748b; margin-top: 4px; }
        .kpi-container { margin-bottom: 25px; }
        .kpi-box { width: 23%; display: inline-block; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-right: 1.5%; text-align: center; }
        .kpi-label { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .kpi-value { font-size: 18px; font-weight: bold; color: #ED7D5C; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        th { background-color: #1B4B5A; color: #ffffff; text-align: left; padding: 7px 10px; font-size: 11px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 7px 10px; font-size: 11px; }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-title">SIGT — Sistema de Gestión de Turnos</div>
        <div class="periodo">Reporte Ejecutivo de Métricas Globales | Período: {{ $metrics['periodo']['desde'] }} al {{ $metrics['periodo']['hasta'] }} ({{ $metrics['periodo']['dias'] }} días)</div>
    </div>

    <div class="kpi-container">
        <div class="kpi-box">
            <div class="kpi-label">Ingresos Totales</div>
            <div class="kpi-value">${{ number_format($metrics['kpis']['ingresos_totales'], 2, ',', '.') }}</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label">Total de Turnos</div>
            <div class="kpi-value">{{ $metrics['kpis']['total_turnos'] }}</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label">Salas Operativas</div>
            <div class="kpi-value">{{ $metrics['kpis']['total_salas_activas'] }}</div>
        </div>
        <div class="kpi-box" style="margin-right: 0;">
            <div class="kpi-label">Usuarios Activos</div>
            <div class="kpi-value">{{ $metrics['kpis']['total_usuarios_act'] }}</div>
        </div>
    </div>

    <h3>Distribución de Turnos por Especialidad</h3>
    <table>
        <thead>
            <tr>
                <th>Especialidad</th>
                <th class="text-right">Cantidad de Turnos</th>
                <th class="text-right">Participación (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($metrics['turnos_por_especialidad'] as $item)
                <tr>
                    <td>{{ $item['especialidad'] }}</td>
                    <td class="text-right">{{ $item['total'] }}</td>
                    <td class="text-right">{{ $item['porcentaje'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="3">No hubo turnos en este período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Ocupación y Rendimiento de Salas</h3>
    <table>
        <thead>
            <tr>
                <th>Sala</th>
                <th>Piso</th>
                <th>Estado</th>
                <th class="text-right">Turnos Realizados</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($metrics['ocupacion_salas'] as $sala)
                <tr>
                    <td>Sala {{ $sala['numero'] }}</td>
                    <td>Piso {{ $sala['piso'] }}</td>
                    <td>{{ $sala['estado'] }}</td>
                    <td class="text-right">{{ $sala['turnos_atendidos'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado automáticamente por SIGT para el Super Administrador el {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>