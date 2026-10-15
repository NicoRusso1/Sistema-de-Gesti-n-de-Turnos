<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class MercadoPagoService
{
    private const API_URL = 'https://api.mercadopago.com';

    public function createPreference(Payment $payment, Appointment $appointment): array
    {
        $returnUrl = rtrim(config('services.mercadopago.return_url'), '/');

        $payload = [
            'items' => [[
                'title' => 'Turno medico #' . $appointment->id,
                'quantity' => 1,
                'currency_id' => 'ARS',
                'unit_price' => (float) $payment->amount,
            ]],
            'external_reference' => (string) $payment->id,
            'back_urls' => [
                'success' => $returnUrl . '/payments/result',
                'pending' => $returnUrl . '/payments/result',
                'failure' => $returnUrl . '/payments/result',
            ],
            'auto_return' => 'approved',
                        'payment_methods' => [
                'excluded_payment_types' => [
                    ['id' => 'ticket'],
                    ['id' => 'atm'],
                    ['id' => 'prepaid_card'],
            ],
            ],
        ];

        return Http::withToken(config('services.mercadopago.access_token'))
            ->acceptJson()
            ->post(self::API_URL . '/checkout/preferences', $payload)
            ->throw()
            ->json();
    }

    public function findPaymentByReference(string $reference): ?array
    {
        $response = Http::withToken(config('services.mercadopago.access_token'))
            ->acceptJson()
            ->get(self::API_URL . '/v1/payments/search', [
                'external_reference' => $reference,
                'sort' => 'date_created',
                'criteria' => 'desc',
                'limit' => 1,
            ])
            ->throw()
            ->json();
            

        return $response['results'][0] ?? null;
    }
}