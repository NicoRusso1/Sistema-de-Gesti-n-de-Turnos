<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentPricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AppointmentController extends Controller
{
    protected AppointmentPricingService $pricingService;

    public function __construct(AppointmentPricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    #[OA\Post(
        path: '/api/appointments',
        summary: 'Registrar la reserva de un turno con cálculo de obra social',
        description: 'Crea una reserva de turno en estado "reservado", valida solapamiento de horarios y calcula el descuento de obra social.',
        tags: ['Turnos'],
        security: [
            ['sanctum' => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Datos para la reserva del turno',
            content: new OA\JsonContent(
                required: ['doctor_id', 'scheduled_at', 'monto_base'],
                properties: [
                    new OA\Property(property: 'doctor_id', type: 'integer', example: 3, description: 'ID del médico'),
                    new OA\Property(property: 'specialty_id', type: 'integer', nullable: true, example: 1, description: 'ID de la especialidad'),
                    new OA\Property(property: 'scheduled_at', type: 'string', example: '2026-10-15 09:15', description: 'Fecha y hora en formato YYYY-MM-DD HH:mm'),
                    new OA\Property(property: 'health_insurance_id', type: 'integer', nullable: true, example: 2, description: 'ID de obra social (omitir o null si es particular)'),
                    new OA\Property(property: 'affiliate_number', type: 'string', nullable: true, example: 'OSDE-98765432', description: 'Número de carnet de afiliado'),
                    new OA\Property(property: 'monto_base', type: 'number', format: 'float', example: 15000.00, description: 'Costo base particular del turno')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Turno reservado exitosamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Turno reservado exitosamente'),
                        new OA\Property(
                            property: 'appointment',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 10),
                                new OA\Property(property: 'patient_id', type: 'integer', example: 5),
                                new OA\Property(property: 'doctor_id', type: 'integer', example: 3),
                                new OA\Property(property: 'scheduled_at', type: 'string', example: '2026-10-15 09:15:00'),
                                new OA\Property(property: 'status', type: 'string', example: 'reservado'),
                                new OA\Property(property: 'health_insurance_id', type: 'integer', nullable: true, example: 2),
                                new OA\Property(property: 'affiliate_number', type: 'string', nullable: true, example: 'OSDE-98765432'),
                                new OA\Property(property: 'monto_base', type: 'number', example: 15000.00),
                                new OA\Property(property: 'monto_descuento', type: 'number', example: 6000.00),
                                new OA\Property(property: 'monto_final', type: 'number', example: 9000.00)
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 409, description: 'El médico ya tiene un turno reservado en ese horario.'),
            new OA\Response(response: 422, description: 'Error de validación en los campos enviados.')
        ]
    )]
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', $validated['scheduled_at']);

        // Verificar que el médico no tenga ya otro turno activo en ese horario exacto
        $exists = Appointment::where('doctor_id', $validated['doctor_id'])
            ->where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['reservado', 'abonado', 'scheduled', 'confirmed'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'El horario seleccionado ya no se encuentra disponible.'
            ], 409);
        }

        // Calcular montos con el servicio GT-38
        $pricing = $this->pricingService->calculate(
            (float) $validated['monto_base'],
            $validated['health_insurance_id'] ?? null
        );

        // Crear turno en estado 'reservado' (GT-39)
        $appointment = Appointment::create([
            'patient_id'          => $user->id,
            'doctor_id'           => $validated['doctor_id'],
            'specialty_id'        => $validated['specialty_id'] ?? null,
            'scheduled_at'        => $scheduledAt,
            'status'              => 'reservado',
            'health_insurance_id' => $validated['health_insurance_id'] ?? null,
            'affiliate_number'    => $validated['affiliate_number'] ?? null,
            'monto_base'          => $pricing['monto_base'],
            'monto_descuento'     => $pricing['monto_descuento'],
            'monto_final'         => $pricing['monto_final'],
        ]);

        return response()->json([
            'message'     => 'Turno reservado exitosamente',
            'appointment' => $appointment->load(['doctor', 'specialty', 'healthInsurance'])
        ], 201);
    }
}