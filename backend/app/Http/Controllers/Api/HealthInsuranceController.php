<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthInsurance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class HealthInsuranceController extends Controller
{
    #[OA\Get(
        path: '/api/health-insurances',
        summary: 'Listar obras sociales',
        tags: ['Obras sociales'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de obras sociales.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.')
        ]
    )]
    public function index(Request $request)
    {
        $query = HealthInsurance::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        return response()->json($query->orderBy('name')->get(), 200);
    }

    #[OA\Post(
        path: '/api/health-insurances',
        summary: 'Crear una obra social',
        tags: ['Obras sociales'],
        security: [
            ['sanctum' => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'discount_percentage'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'OSDE'),
                    new OA\Property(property: 'discount_percentage', type: 'number', format: 'float', example: 30),
                    new OA\Property(property: 'status', type: 'integer', enum: [0, 1], example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Obra social creada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('health_insurances', 'name')],
            'discount_percentage' => ['required', 'numeric', 'between:0,100'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ], [
            'name.unique' => 'La obra social ya se encuentra registrada.',
        ]);

        $healthInsurance = HealthInsurance::create($validated);

        return response()->json([
            'message' => 'Obra social creada correctamente',
            'health_insurance' => $healthInsurance->fresh()
        ], 201);
    }

    #[OA\Get(
        path: '/api/health-insurances/{id}',
        summary: 'Obtener una obra social',
        tags: ['Obras sociales'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID de la obra social',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Obra social encontrada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Obra social no encontrada.')
        ]
    )]
    public function show(string $id)
    {
        $healthInsurance = HealthInsurance::find($id);

        if (!$healthInsurance) {
            return response()->json([
                'message' => 'Obra social no encontrada'
            ], 404);
        }

        return response()->json($healthInsurance, 200);
    }

    #[OA\Put(
        path: '/api/health-insurances/{id}',
        summary: 'Actualizar una obra social',
        tags: ['Obras sociales'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID de la obra social',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'OSDE'),
                    new OA\Property(property: 'discount_percentage', type: 'number', format: 'float', example: 35),
                    new OA\Property(property: 'status', type: 'integer', enum: [0, 1], example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Obra social actualizada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Obra social no encontrada.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $healthInsurance = HealthInsurance::find($id);

        if (!$healthInsurance) {
            return response()->json([
                'message' => 'Obra social no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('health_insurances', 'name')->ignore($healthInsurance->id)],
            'discount_percentage' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
        ], [
            'name.unique' => 'La obra social ya se encuentra registrada.',
        ]);

        $healthInsurance->update($validated);

        return response()->json([
            'message' => 'Obra social actualizada correctamente',
            'health_insurance' => $healthInsurance->fresh()
        ], 200);
    }

    #[OA\Delete(
        path: '/api/health-insurances/{id}',
        summary: 'Dar de baja una obra social',
        tags: ['Obras sociales'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID de la obra social',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Obra social dada de baja correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Obra social no encontrada.')
        ]
    )]
    public function destroy(string $id)
    {
        $healthInsurance = HealthInsurance::find($id);

        if (!$healthInsurance) {
            return response()->json([
                'message' => 'Obra social no encontrada'
            ], 404);
        }

        $healthInsurance->status = 0;
        $healthInsurance->save();

        return response()->json([
            'message' => 'Obra social dada de baja correctamente'
        ], 200);
    }

    #[OA\Post(
        path: '/api/health-insurances/{id}/restore',
        summary: 'Reactivar una obra social',
        tags: ['Obras sociales'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID de la obra social',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Obra social reactivada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Obra social no encontrada.')
        ]
    )]
    public function restore(string $id)
    {
        $healthInsurance = HealthInsurance::find($id);

        if (!$healthInsurance) {
            return response()->json([
                'message' => 'Obra social no encontrada'
            ], 404);
        }

        $healthInsurance->status = 1;
        $healthInsurance->save();

        return response()->json([
            'message' => 'Obra social reactivada correctamente'
        ], 200);
    }
}