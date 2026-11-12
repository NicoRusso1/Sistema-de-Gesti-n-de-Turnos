<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    private string $accessToken;
    private string $apiUrl;

    public function __construct()
    {
        $this->accessToken = config('services.mercadopago.access_token', '');
        $this->apiUrl = 'https://api.mercadopago.com';
    }

    /**
     * Procesa un cobro directo con tarjeta tokenizada (PCI-DSS compliant) o pasarela mock.
     *
     * @param float $amount Monto final a cobrar
     * @param string $token Token de tarjeta generado en frontend
     * @param string $description Concepto
     * @param string $payerEmail Email del pagador
     * @return array{success: bool, transaction_id: ?string, status: string, error: ?string}
     */
    public function cobrarConToken(float $amount, string $token, string $description, string $payerEmail): array
    {
        // En ambiente de testing o si no hay token configurado, responder según token simulado
        if (app()->environment('testing') || empty($this->accessToken)) {
            if ($token === 'tok_rejected' || $token === 'invalid_token') {
                return [
                    'success'        => false,
                    'transaction_id' => null,
                    'status'         => 'rechazado',
                    'error'          => 'Fondos insuficientes o pago declinado por el emisor',
                ];
            }

            return [
                'success'        => true,
                'transaction_id' => 'GATEWAY-MOCK-' . uniqid(),
                'status'         => 'aprobado',
                'error'          => null,
            ];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->post("{$this->apiUrl}/v1/payments", [
                    'transaction_amount' => $amount,
                    'token'              => $token,
                    'description'        => $description,
                    'installments'       => 1,
                    'payment_method_id'  => 'visa',
                    'payer'              => [
                        'email' => $payerEmail,
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $isApproved = ($data['status'] ?? '') === 'approved';

                return [
                    'success'        => $isApproved,
                    'transaction_id' => (string) ($data['id'] ?? uniqid()),
                    'status'         => $isApproved ? 'aprobado' : 'rechazado',
                    'error'          => $isApproved ? null : ($data['status_detail'] ?? 'Pago no aprobado'),
                ];
            }

            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'rechazado',
                'error'          => $response->json('message') ?? 'Error en la pasarela de pagos',
            ];
        } catch (Exception $e) {
            Log::error('PaymentGatewayService Error: ' . $e->getMessage());

            return [
                'success'        => false,
                'transaction_id' => null,
                'status'         => 'rechazado',
                'error'          => 'Fallo de comunicación con la pasarela de pagos',
            ];
        }
    }
}