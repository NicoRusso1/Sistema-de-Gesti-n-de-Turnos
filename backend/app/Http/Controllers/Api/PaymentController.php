<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethodName;
use App\Enums\PaymentStatusName;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentStatus;
use App\Services\MercadoPagoService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PaymentController extends Controller
{
    protected MercadoPagoService $mercadoPago;

    public function __construct(MercadoPagoService $mercadoPago)
    {
        $this->mercadoPago = $mercadoPago;
    }

        #[OA\Post(
        path: '/api/appointments/{id}/payments',
        summary: 'Iniciar el pago de un turno con MercadoPago',
        description: 'Crea un pago pendiente para un turno en estado "reservado" y devuelve el link de checkout de MercadoPago.',
        tags: ['Pagos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del turno a pagar',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 10
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Pago iniciado.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Pago iniciado'),
                        new OA\Property(property: 'payment_id', type: 'integer', example: 1),
                        new OA\Property(property: 'checkout_url', type: 'string', example: 'https://sandbox.mercadopago.com.ar/checkout/v1/redirect?pref_id=123')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El turno pertenece a otro paciente.'),
            new OA\Response(response: 404, description: 'Turno no encontrado.'),
            new OA\Response(response: 409, description: 'El turno no está pendiente de pago.'),
            new OA\Response(response: 422, description: 'El turno no tiene un monto a pagar.'),
            new OA\Response(response: 502, description: 'No se pudo iniciar el pago con MercadoPago.')
        ]
    )]

    public function store(Request $request, $id): JsonResponse
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        if ((int) $appointment->patient_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'No podés pagar el turno de otro paciente'], 403);
        }

        if ($appointment->status !== 'reservado') {
            return response()->json(['message' => 'El turno no está pendiente de pago'], 409);
        }

        if ((float) $appointment->monto_final <= 0) {
            return response()->json(['message' => 'El turno no tiene un monto a pagar'], 422);
        }

        $pending = PaymentStatus::where('name', PaymentStatusName::Pending->value)->firstOrFail();

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'payment_status_id' => $pending->id,
            'amount' => $appointment->monto_final,
        ]);

        try {
            $preference = $this->mercadoPago->createPreference($payment, $appointment);
                } catch (RequestException $e) {
            logger()->error('MercadoPago: error al crear la preferencia', [
                'status' => $e->response->status(),
                'body' => $e->response->body(),
            ]);

            $payment->delete();

            return response()->json(['message' => 'No se pudo iniciar el pago con MercadoPago'], 502);
        }

        $payment->update(['provider_preference_id' => $preference['id']]);

        $checkoutUrl = config('services.mercadopago.sandbox')
            ? ($preference['sandbox_init_point'] ?? $preference['init_point'])
            : $preference['init_point'];

        return response()->json([
            'message' => 'Pago iniciado',
            'payment_id' => $payment->id,
            'checkout_url' => $checkoutUrl,
        ], 201);
    }


        #[OA\Post(
        path: '/api/payments/{id}/verify',
        summary: 'Verificar el resultado de un pago con MercadoPago',
        description: 'Consulta a MercadoPago el resultado del pago, actualiza su estado y, si fue aprobado, el turno pasa a "abonado".',
        tags: ['Pagos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID del pago (payment_id devuelto al iniciar el pago)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado actual del pago.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Pago actualizado'),
                        new OA\Property(
                            property: 'payment',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'amount', type: 'string', example: '9000.00'),
                                new OA\Property(property: 'status', type: 'object', properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'approved')
                                ]),
                                new OA\Property(property: 'method', type: 'object', nullable: true, properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'credit')
                                ]),
                                new OA\Property(property: 'appointment', type: 'object', properties: [
                                    new OA\Property(property: 'status', type: 'string', example: 'abonado')
                                ])
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'El pago pertenece a otro paciente.'),
            new OA\Response(response: 404, description: 'Pago no encontrado.'),
            new OA\Response(response: 409, description: 'El monto pagado no coincide con el del turno.'),
            new OA\Response(response: 502, description: 'No se pudo consultar MercadoPago.')
        ]
    )]


    public function verify(Request $request, $id): JsonResponse
    {
        $payment = Payment::with('appointment')->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        if ((int) $payment->appointment->patient_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'No podés consultar el pago de otro paciente'], 403);
        }

        $approved = PaymentStatus::where('name', PaymentStatusName::Approved->value)->firstOrFail();

        if ((int) $payment->payment_status_id === (int) $approved->id) {
            return response()->json([
                'message' => 'El pago ya estaba aprobado',
                'payment' => $payment->load(['method', 'status', 'appointment']),
            ], 200);
        }

        try {
            $result = $this->mercadoPago->findPaymentByReference((string) $payment->id);
        } catch (RequestException $e) {
            return response()->json(['message' => 'No se pudo consultar MercadoPago'], 502);
        }

        if (!$result) {
            return response()->json([
                'message' => 'Todavía no hay un pago registrado',
                'payment' => $payment->load(['method', 'status', 'appointment']),
            ], 200);
        }

        if (abs((float) $result['transaction_amount'] - (float) $payment->amount) > 0.01) {
            return response()->json(['message' => 'El monto pagado no coincide con el del turno'], 409);
        }

        $statusName = match ($result['status']) {
            'approved' => PaymentStatusName::Approved,
            'rejected', 'cancelled' => PaymentStatusName::Rejected,
            default => PaymentStatusName::Pending,
        };

        $methodName = match ($result['payment_type_id'] ?? null) {
            'credit_card' => PaymentMethodName::Credit,
            'debit_card' => PaymentMethodName::Debit,
            'bank_transfer' => PaymentMethodName::Transfer,
            default => null,
        };

        $status = PaymentStatus::where('name', $statusName->value)->firstOrFail();
        $method = $methodName ? PaymentMethod::where('name', $methodName->value)->first() : null;

        $payment->update([
            'payment_status_id' => $status->id,
            'payment_method_id' => $method?->id,
            'provider_payment_id' => (string) $result['id'],
            'payment_date' => $statusName === PaymentStatusName::Approved ? now() : null,
        ]);

        return response()->json([
            'message' => 'Pago actualizado',
            'payment' => $payment->fresh()->load(['method', 'status', 'appointment']),
        ], 200);
    }
}