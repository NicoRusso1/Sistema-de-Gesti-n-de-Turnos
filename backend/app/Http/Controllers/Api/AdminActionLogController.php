<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAdminActionLogsRequest;
use App\Http\Resources\AdminActionLogResource;
use App\Models\AdminActionLog;
use OpenApi\Attributes as OA;

class AdminActionLogController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    #[OA\Get(
        path: '/api/auditoria',
        summary: 'Historial de auditoría de acciones administrativas',
        description: 'Lista paginada de las acciones registradas sobre administradores, ordenada por fecha descendente. Solo accesible para el SuperAdmin.',
        tags: ['Auditoría'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'action', in: 'query', required: false, description: 'Filtra por tipo de acción', schema: new OA\Schema(type: 'string', enum: ['grant_owner', 'revoke_owner', 'activate', 'deactivate', 'grant_admin', 'revoke_admin'])),
            new OA\Parameter(name: 'affected_admin_id', in: 'query', required: false, description: 'ID del administrador afectado', schema: new OA\Schema(type: 'integer', minimum: 1), example: 2),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, description: 'Fecha desde (inclusiva)', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-01-01'),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, description: 'Fecha hasta (inclusiva). No puede ser anterior a date_from', schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-01-31'),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Resultados por página (1 a 100, por defecto 15)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Número de página', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Página de registros del historial.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 12),
                            new OA\Property(property: 'date', type: 'string', format: 'date-time', example: '2026-03-01T10:00:00+00:00'),
                            new OA\Property(property: 'action', properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'grant_admin'),
                                new OA\Property(property: 'label', type: 'string', example: 'Otorgó rol de administrador'),
                            ], type: 'object'),
                            new OA\Property(property: 'actor', properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Ana Lopez'),
                            ], type: 'object'),
                            new OA\Property(property: 'affected', properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                new OA\Property(property: 'name', type: 'string', example: 'Beto Ruiz'),
                            ], type: 'object'),
                        ],
                        type: 'object'
                    )),
                    new OA\Property(property: 'links', type: 'object'),
                    new OA\Property(property: 'meta', properties: [
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'last_page', type: 'integer', example: 3),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 40),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 401,
                description: 'No autenticado.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')])
            ),
            new OA\Response(
                response: 403,
                description: 'Solo el Super Administrador puede acceder.',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Solo el Super Administrador puede realizar esta acción')])
            ),
            new OA\Response(
                response: 422,
                description: 'Filtros inválidos (acción inexistente, fecha inválida, date_to anterior a date_from, per_page fuera de rango).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'La fecha hasta no puede ser anterior a la fecha desde.'),
                    new OA\Property(property: 'errors', type: 'object', example: ['date_to' => ['La fecha hasta no puede ser anterior a la fecha desde.']]),
                ])
            ),
        ]
    )]
    public function index(ListAdminActionLogsRequest $request)
    {
        $logs = AdminActionLog::query()
            ->with(['action', 'superAdmin', 'affectedAdmin'])
            ->when($request->filled('action'), fn ($query) => $query->whereHas(
                'action',
                fn ($q) => $q->where('name', $request->input('action'))
            ))
            ->when($request->filled('affected_admin_id'), fn ($query) => $query->where(
                'affected_admin_id',
                $request->integer('affected_admin_id')
            ))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', self::DEFAULT_PER_PAGE));

        return AdminActionLogResource::collection($logs);
    }
}
