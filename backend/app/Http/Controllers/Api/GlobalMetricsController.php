<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GlobalMetricsService;
use App\Services\MetricsExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class GlobalMetricsController extends Controller
{
    protected GlobalMetricsService $metricsService;
    protected MetricsExportService $exportService;

    public function __construct(
        GlobalMetricsService $metricsService,
        MetricsExportService $exportService
    ) {
        $this->metricsService = $metricsService;
        $this->exportService = $exportService;
    }

    #[OA\Get(
        path: '/api/metricas/globales',
        summary: 'Consultar métricas globales del centro médico (Solo Super Administrador)',
        tags: ['Métricas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'fecha_desde', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'fecha_hasta', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Métricas agregadas obtenidas con éxito.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Acceso denegado: solo SuperAdmin.')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->parsePeriodo($request);
        $data = $this->metricsService->getMetrics($desde, $hasta);

        return response()->json($data, 200);
    }

    #[OA\Get(
        path: '/api/metricas/globales/export',
        summary: 'Exportar reporte de métricas en PDF o CSV (Solo Super Administrador)',
        tags: ['Métricas'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'formato', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['pdf', 'csv'], default: 'pdf')),
            new OA\Parameter(name: 'fecha_desde', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'fecha_hasta', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Archivo binario descargado.'),
            new OA\Response(response: 403, description: 'No autorizado.')
        ]
    )]
    public function export(Request $request)
    {
        [$desde, $hasta] = $this->parsePeriodo($request);
        $data = $this->metricsService->getMetrics($desde, $hasta);
        $formato = strtolower($request->query('formato', 'pdf'));

        if ($formato === 'csv') {
            return $this->exportService->exportCsv($data);
        }

        return $this->exportService->exportPdf($data);
    }

    private function parsePeriodo(Request $request): array
    {
        $desde = $request->filled('fecha_desde')
            ? Carbon::parse($request->query('fecha_desde'))->startOfDay()
            : now()->subDays(30)->startOfDay();

        $hasta = $request->filled('fecha_hasta')
            ? Carbon::parse($request->query('fecha_hasta'))->endOfDay()
            : now()->endOfDay();

        return [$desde, $hasta];
    }
}