<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;

class MetricsExportService
{
    /**
     * Genera la descarga en formato PDF utilizando Dompdf.
     */
    public function exportPdf(array $metrics): Response
    {
        $pdf = Pdf::loadView('reports.global_metrics', ['metrics' => $metrics])
            ->setPaper('a4', 'portrait');

        $fileName = 'reporte-metricas-globales-' . $metrics['periodo']['desde'] . '_a_' . $metrics['periodo']['hasta'] . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Genera la descarga en formato CSV sin librerías externas.
     */
    public function exportCsv(array $metrics): StreamedResponse
    {
        $fileName = 'reporte-metricas-globales-' . $metrics['periodo']['desde'] . '_a_' . $metrics['periodo']['hasta'] . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($metrics) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

            // Encabezado
            fputcsv($handle, ['REPORTE DE MÉTRICAS GLOBALES - SIGT']);
            fputcsv($handle, ['Período Desde', $metrics['periodo']['desde'], 'Hasta', $metrics['periodo']['hasta']]);
            fputcsv($handle, []);

            // KPIs
            fputcsv($handle, ['INDICADOR CLAVE', 'VALOR']);
            fputcsv($handle, ['Ingresos Totales', '$' . number_format($metrics['kpis']['ingresos_totales'], 2)]);
            fputcsv($handle, ['Total de Turnos', $metrics['kpis']['total_turnos']]);
            fputcsv($handle, ['Salas Operativas', $metrics['kpis']['total_salas_activas']]);
            fputcsv($handle, ['Usuarios Activos', $metrics['kpis']['total_usuarios_act']]);
            fputcsv($handle, []);

            // Especialidades
            fputcsv($handle, ['TURNOS POR ESPECIALIDAD']);
            fputcsv($handle, ['Especialidad', 'Cantidad', 'Porcentaje']);
            foreach ($metrics['turnos_por_especialidad'] as $item) {
                fputcsv($handle, [$item['especialidad'], $item['total'], $item['porcentaje'] . '%']);
            }
            fputcsv($handle, []);

            // Salas
            fputcsv($handle, ['UTILIZACIÓN DE SALAS']);
            fputcsv($handle, ['Sala N°', 'Piso', 'Estado', 'Turnos Atendidos']);
            foreach ($metrics['ocupacion_salas'] as $sala) {
                fputcsv($handle, [$sala['numero'], $sala['piso'], $sala['estado'], $sala['turnos_atendidos']]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}