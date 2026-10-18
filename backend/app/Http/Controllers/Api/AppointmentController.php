<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentNotificationService;
use App\Services\AppointmentPricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AppointmentController extends Controller
{
    protected AppointmentPricingService $pricingService;
    protected AppointmentNotificationService $notificationService;

    public function __construct(
        AppointmentPricingService $pricingService,
        AppointmentNotificationService $notificationService
    ) {
        $this->pricingService = $pricingService;
        $this->notificationService = $notificationService;
    }

    #[OA\Get(
        path: '/api/appointments',
        summary: 'Listar la agenda de turnos según el rol / scope del usuario (GT-54 y GT-55)',
        description: 'Retorna los turnos visibles para el usuario logueado según su tipo de usuario y permisos activos: '
            . 'Paciente con view_own_appointments → solo sus turnos (scope "own"); '
            . 'Médico con view_assigned_patients → solo los turnos donde es el médico asignado (scope "assigned"); '
            . 'Secretaria con view_all_appointments o Administrador → todos los turnos (scope "all"). '
            . 'Los filtros doctor_id y patient_id solo se aplican con scope "all". '
            . 'La respuesta es paginada e incluye el campo "scope" aplicado.',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['reservado', 'abonado', 'scheduled', 'confirmed', 'cancelado'])),
            new OA\Parameter(name: 'date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), example: '2026-10-15'),
            new OA\Parameter(name: 'doctor_id', in: 'query', required: false, description: 'Solo con scope "all"', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'patient_id', in: 'query', required: false, description: 'Solo con scope "all"', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de turnos devuelto correctamente.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'scope', type: 'string', enum: ['own', 'assigned', 'all'], example: 'own', description: 'Alcance aplicado a la consulta'),
                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'last_page', type: 'integer', example: 1),
                        new OA\Property(property: 'per_page', type: 'integer', example: 15),
                        new OA\Property(property: 'total', type: 'integer', example: 3)
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El usuario no tiene permisos activos para ver la agenda de turnos.')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // Resuelto por el middleware appointment_scope (GT-54)
        $scope = $request->attributes->get('appointment_scope');

        // GT-55: filtro por scope del usuario logueado
        $query = Appointment::with(['doctor', 'patient', 'specialty', 'sala', 'healthInsurance'])
            ->visibleTo($scope, $user);

        if ($scope === Appointment::SCOPE_ALL) {
            if ($request->filled('patient_id')) {
                $query->where('patient_id', $request->patient_id);
            }

            if ($request->filled('doctor_id')) {
                $query->where('doctor_id', $request->doctor_id);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        $appointments = $query->orderBy('scheduled_at', 'desc')->paginate(15);

        return response()->json(array_merge(['scope' => $scope], $appointments->toArray()), 200);
    }

    #[OA\Post(
        path: '/api/appointments',
        summary: 'Registrar la reserva de un turno con cálculo de obra social',
        description: 'Crea una reserva de turno en estado "reservado", valida solapamiento de horarios y calcula el descuento de obra social.',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Datos para la reserva del turno',
            content: new OA\JsonContent(
                required: ['doctor_id', 'scheduled_at', 'monto_base'],
                properties: [
                    new OA\Property(property: 'doctor_id', type: 'integer', example: 3, description: 'ID del médico'),
                    new OA\Property(property: 'specialty_id', type: 'integer', nullable: true, example: 1, description: 'ID de la especialidad'),
                    new OA\Property(property: 'sala_id', type: 'integer', nullable: true, example: 2, description: 'ID de la sala opcional'),
                    new OA\Property(property: 'scheduled_at', type: 'string', example: '2026-10-15 09:15', description: 'Fecha y hora en formato YYYY-MM-DD HH:mm'),
                    new OA\Property(property: 'health_insurance_id', type: 'integer', nullable: true, example: 2, description: 'ID de obra social (omitir o null si es particular)'),
                    new OA\Property(property: 'affiliate_number', type: 'string', nullable: true, example: 'OSDE-98765432', description: 'Número de carnet de afiliado'),
                    new OA\Property(property: 'monto_base', type: 'number', format: 'float', example: 15000.00, description: 'Costo base particular del turno')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Turno reservado exitosamente.'),
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
            'sala_id'             => $validated['sala_id'] ?? null,
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
            'appointment' => $appointment->load(['doctor', 'specialty', 'sala', 'healthInsurance'])
        ], 201);
    }

    #[OA\Patch(
        path: '/api/appointments/{id}/cancel',
        summary: 'Cancelar un turno (GT-57 y GT-58)',
        description: 'Cambia el estado del turno a "cancelado", libera la sala/horario en la agenda y genera la notificación correspondiente (GT-59).',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del turno a cancelar', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Imprevisto laboral', description: 'Motivo de la cancelación')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Turno cancelado exitosamente y agenda liberada.'),
            new OA\Response(response: 400, description: 'El turno ya se encuentra cancelado.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para cancelar turnos.'),
            new OA\Response(response: 404, description: 'Turno no encontrado.')
        ]
    )]
    public function cancel(CancelAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::with(['doctor', 'patient'])->find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado.'], 404);
        }

        if ($appointment->status === 'cancelado') {
            return response()->json(['message' => 'El turno ya ha sido cancelado previamente.'], 400);
        }

        $reason = $request->input('reason');

        // GT-58: Actualizar estado a 'cancelado' para liberar la agenda y la sala
        $appointment->update([
            'status'              => 'cancelado',
            'cancellation_reason' => $reason,
        ]);

        // GT-59: Registrar la notificación en la tabla de notificaciones
        $this->notificationService->notifyCancellation($appointment, $reason);

        return response()->json([
            'message'     => 'Turno cancelado correctamente. La agenda y la sala han sido liberadas.',
            'appointment' => $appointment->fresh(['doctor', 'specialty', 'sala'])
        ], 200);
    }

    #[OA\Patch(
        path: '/api/appointments/{id}/reschedule',
        summary: 'Reprogramar un turno existente',
        description: 'Modifica la fecha y hora de un turno existente validando la disponibilidad del horario en la agenda del médico.',
        tags: ['Turnos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del turno a reprogramar', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['scheduled_at'],
                properties: [
                    new OA\Property(property: 'scheduled_at', type: 'string', example: '2026-10-20 11:30', description: 'Nueva fecha y hora (YYYY-MM-DD HH:mm)'),
                    new OA\Property(property: 'sala_id', type: 'integer', nullable: true, example: 1, description: 'ID de sala opcional')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Turno reprogramado exitosamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para modificar horarios.'),
            new OA\Response(response: 404, description: 'Turno no encontrado.'),
            new OA\Response(response: 409, description: 'El nuevo horario ya no está disponible.')
        ]
    )]
    public function reschedule(RescheduleAppointmentRequest $request, string $id): JsonResponse
    {
        $appointment = Appointment::with(['doctor', 'patient'])->find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado.'], 404);
        }

        if ($appointment->status === 'cancelado') {
            return response()->json(['message' => 'No es posible reprogramar un turno que ya está cancelado. Debe reservar uno nuevo.'], 400);
        }

        $validated = $request->validated();
        $newScheduledAt = Carbon::createFromFormat('Y-m-d H:i', $validated['scheduled_at']);

        // Validar que el médico no tenga ya otro turno activo en ese nuevo horario
        $conflict = Appointment::where('doctor_id', $appointment->doctor_id)
            ->where('id', '!=', $appointment->id)
            ->where('scheduled_at', $newScheduledAt)
            ->whereIn('status', ['reservado', 'abonado', 'scheduled', 'confirmed'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'El horario seleccionado ya se encuentra ocupado por otro turno.'
            ], 409);
        }

        $previousDate = $appointment->scheduled_at->format('d/m/Y H:i');

        $appointment->update([
            'scheduled_at' => $newScheduledAt,
            'sala_id'      => $validated['sala_id'] ?? $appointment->sala_id,
            // Si estaba reservado o confirmado, se mantiene activo
            'status'       => $appointment->status === 'cancelado' ? 'reservado' : $appointment->status,
        ]);

        // Registrar notificación de reprogramación (GT-59)
        $this->notificationService->notifyRescheduling($appointment, $previousDate);

        return response()->json([
            'message'     => 'Turno reprogramado correctamente.',
            'appointment' => $appointment->fresh(['doctor', 'specialty', 'sala'])
        ], 200);
    }
}