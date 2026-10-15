<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientHealthInsuranceRequest;
use App\Models\PatientHealthInsurance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PatientHealthInsuranceController extends Controller
{
    #[OA\Get(
        path: '/api/me/health-insurance',
        summary: 'Ver la obra social cargada del paciente autenticado',
        tags: ['Obra social del paciente'],
        security: [
            ['sanctum' => []]
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Obra social del paciente, o null si no tiene ninguna cargada.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'affiliation',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'health_insurance_id', type: 'integer', example: 2),
                                new OA\Property(property: 'member_number', type: 'string', example: 'OSDE-98765432'),
                                new OA\Property(
                                    property: 'health_insurance',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 2),
                                        new OA\Property(property: 'name', type: 'string', example: 'OSDE'),
                                        new OA\Property(property: 'discount_percentage', type: 'string', example: '30.00')
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.')
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $affiliation = PatientHealthInsurance::with('healthInsurance')
            ->where('patient_id', $request->user()->id)
            ->first();

        return response()->json(['affiliation' => $affiliation], 200);
    }

    #[OA\Put(
        path: '/api/me/health-insurance',
        summary: 'Cargar o modificar la obra social del paciente autenticado',
        description: 'Asocia la obra social al paciente, o reemplaza la que ya tenía. La obra social tiene que estar activa.',
        tags: ['Obra social del paciente'],
        security: [
            ['sanctum' => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['health_insurance_id', 'member_number'],
                properties: [
                    new OA\Property(property: 'health_insurance_id', type: 'integer', example: 2, description: 'ID de una obra social activa'),
                    new OA\Property(property: 'member_number', type: 'string', example: 'OSDE-98765432', description: 'Número de carnet de afiliado')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Obra social modificada.'),
            new OA\Response(response: 201, description: 'Obra social cargada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 422, description: 'Obra social inexistente o inactiva, o falta el número de afiliado.')
        ]
    )]
    public function update(StorePatientHealthInsuranceRequest $request): JsonResponse
    {
        $affiliation = PatientHealthInsurance::updateOrCreate(
            ['patient_id' => $request->user()->id],
            [
                'health_insurance_id' => $request->validated('health_insurance_id'),
                'member_number' => $request->validated('member_number'),
            ]
        );

        return response()->json([
            'message' => 'Obra social guardada correctamente',
            'affiliation' => $affiliation->load('healthInsurance'),
        ], $affiliation->wasRecentlyCreated ? 201 : 200);
    }

    #[OA\Delete(
        path: '/api/me/health-insurance',
        summary: 'Eliminar la obra social del paciente autenticado',
        tags: ['Obra social del paciente'],
        security: [
            ['sanctum' => []]
        ],
        responses: [
            new OA\Response(response: 200, description: 'Obra social eliminada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'El paciente no tiene una obra social cargada.')
        ]
    )]
    public function destroy(Request $request): JsonResponse
    {
        $affiliation = PatientHealthInsurance::where('patient_id', $request->user()->id)->first();

        if (!$affiliation) {
            return response()->json([
                'message' => 'No tenés una obra social cargada'
            ], 404);
        }

        $affiliation->delete();

        return response()->json([
            'message' => 'Obra social eliminada correctamente'
        ], 200);
    }
}