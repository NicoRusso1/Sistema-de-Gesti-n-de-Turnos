<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Specialty;
use App\Services\AppointmentStatusReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * GT-67: reporte de turnos por estado (Administrador y Super Administrador).
 */
class AppointmentStatusReportController extends Controller
{
    public function __construct(private AppointmentStatusReportService $report)
    {
    }

    #[OA\Get(
        path: '/api/reportes/turnos-por-estado',
        summary: 'Reporte de turnos por estado (Administrador / Super Administrador)',
        description: 'Totales de turnos por estado (reservado, abonado, en espera, completado y cancelado) con la descripción de cada uno. Todos los filtros son opcionales; sin fechas abarca todos los turnos. Incluye las especialidades y médicos disponibles para armar los filtros.',
        tags: ['Reportes'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'fecha_desde', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Fecha del turno desde (inclusive).'),
            new OA\Parameter(name: 'fecha_hasta', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Fecha del turno hasta (inclusive, día completo).'),
            new OA\Parameter(name: 'specialty_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'doctor_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Reporte generado.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'estados', type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'estado', type: 'string', example: 'abonado'),
                    new OA\Property(property: 'etiqueta', type: 'string', example: 'Abonado'),
                    new OA\Property(property: 'descripcion', type: 'string'),
                    new OA\Property(property: 'cantidad', type: 'integer', example: 12),
                    new OA\Property(property: 'porcentaje', type: 'number', example: 40.0),
                ])),
                new OA\Property(property: 'total', type: 'integer', example: 30),
                new OA\Property(property: 'filtros', type: 'object'),
                new OA\Property(property: 'generado_en', type: 'string', format: 'date-time'),
                new OA\Property(property: 'opciones', type: 'object', description: 'Especialidades y médicos para los filtros.'),
            ])),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Solo Administrador o Super Administrador.'),
            new OA\Response(response: 422, description: 'Filtros inválidos.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde'  => ['nullable', 'date'],
            'fecha_hasta'  => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'specialty_id' => ['nullable', 'integer', Rule::exists('specialties', 'id')],
            'doctor_id'    => ['nullable', 'integer', Rule::exists('patients', 'id')],
        ], [
            'fecha_hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a la fecha "desde".',
            'specialty_id.exists'        => 'La especialidad seleccionada no existe.',
            'doctor_id.exists'           => 'El médico seleccionado no existe.',
        ]);

        $data = $this->report->generate(
            isset($validated['fecha_desde']) ? Carbon::parse($validated['fecha_desde'])->startOfDay() : null,
            isset($validated['fecha_hasta']) ? Carbon::parse($validated['fecha_hasta'])->endOfDay() : null,
            isset($validated['specialty_id']) ? (int) $validated['specialty_id'] : null,
            isset($validated['doctor_id']) ? (int) $validated['doctor_id'] : null,
        );

        $data['opciones'] = [
            'especialidades' => Specialty::orderBy('name')->get(['id', 'name']),
            'medicos'        => Patient::doctors()
                ->orderBy('last_name')->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (Patient $d) => ['id' => $d->id, 'nombre' => "{$d->first_name} {$d->last_name}"]),
        ];

        return response()->json($data, 200);
    }
}
