<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrarPagoRequest;
use App\Models\Appointment;
use App\Models\Pago;
use App\Services\PaymentCalculationService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PagoProcesoController extends Controller
{
    protected PaymentCalculationService $calculator;
    protected PaymentGatewayService $gateway;

    public function __construct(
        PaymentCalculationService $calculator,
        PaymentGatewayService $gateway
    ) {
        $this->calculator = $calculator;
        $this->gateway = $gateway;
    }

    #[OA\Post(
        path: '/api/pagos',
        summary: 'Registrar pago de turno y aplicar descuento de obra social',
        tags: ['Pagos'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['turno_id', 'token_pago'],
                properties: [
                    new OA\Property(property: 'turno_id', type: 'integer', example: 12),
                    new OA\Property(property: 'token_pago', type: 'string', example: 'tok_visa_12345'),
                    new OA\Property(property: 'metodo_pago', type: 'string', example: 'tarjeta_credito')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pago aprobado y turno confirmado.'),
            new OA\Response(response: 402, description: 'Pago rechazado por la entidad bancaria.'),
            new OA\Response(response: 403, description: 'Turno no pertenece al paciente logueado.'),
            new OA\Response(response: 409, description: 'El turno no se encuentra en estado reservado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
    public function store(RegistrarPagoRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $turno = Appointment::with(['patient', 'doctor'])->find($validated['turno_id']);

        // Validación 1: El turno debe pertenecer al paciente logueado (a menos que sea admin)
        if (!$user->hasAdminAccess() && (int) $turno->patient_id !== (int) $user->id) {
            return response()->json(['message' => 'No podés abonar un turno de otro paciente.'], 403);
        }

        // Validación 2: El turno debe estar en estado reservado
        if ($turno->status !== 'reservado') {
            return response()->json([
                'message' => "El turno no se encuentra pendiente de pago (Estado actual: {$turno->status})."
            ], 409);
        }

        // GT-120: Calcular montos y descuentos vigentes de obra social
        $montoBase = (float) ($turno->monto_base > 0 ? $turno->monto_base : 15000.00);
        $calculo = $this->calculator->calcularMonto($montoBase, $turno->patient);

        // GT-118: Crear registro en BD en estado 'pendiente' para trazabilidad
        $pago = Pago::create([
            'turno_id'           => $turno->id,
            'monto_original'     => $calculo['monto_original'],
            'descuento_aplicado' => $calculo['descuento_aplicado'],
            'monto_final'        => $calculo['monto_final'],
            'estado'             => 'pendiente',
            'metodo_pago'        => $validated['metodo_pago'] ?? 'tarjeta',
        ]);

        // GT-125: Ejecutar cobro con la pasarela externa
        $gatewayResult = $this->gateway->cobrarConToken(
            $calculo['monto_final'],
            $validated['token_pago'],
            "Pago de turno médico #{$turno->id}",
            $user->email
        );

        // Manejo de Estados
        if ($gatewayResult['success']) {
            DB::transaction(function () use ($pago, $turno, $gatewayResult, $calculo) {
                // Actualizar estado de Pago a 'aprobado'
                $pago->update([
                    'estado'         => 'aprobado',
                    'transaccion_id' => $gatewayResult['transaction_id'],
                    'fecha_pago'     => now(),
                ]);

                // Actualizar el turno a 'abonado' guardando la trazabilidad de montos
                $turno->update([
                    'status'          => 'abonado',
                    'monto_base'      => $calculo['monto_original'],
                    'monto_descuento' => $calculo['descuento_aplicado'],
                    'monto_final'     => $calculo['monto_final'],
                ]);
            });

            return response()->json([
                'message'      => 'Pago procesado y aprobado exitosamente.',
                'estado'       => 'aprobado',
                'pago'         => $pago->fresh(),
                'trazabilidad' => $calculo,
            ], 200);
        }

        // Si fue rechazado
        $pago->update([
            'estado'        => 'rechazado',
            'error_mensaje' => $gatewayResult['error'],
        ]);

        return response()->json([
            'message'      => 'El pago fue rechazado por la pasarela de pagos.',
            'estado'       => 'rechazado',
            'motivo'       => $gatewayResult['error'],
            'pago'         => $pago->fresh(),
            'trazabilidad' => $calculo,
        ], 402);
    }
}