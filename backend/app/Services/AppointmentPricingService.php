<?php

namespace App\Services;

use App\Models\HealthInsurance;

class AppointmentPricingService
{
    /**
     * Calcula monto_descuento y monto_final a partir del monto_base y la obra social.
     *
     * @param float $montoBase
     * @param int|null $healthInsuranceId
     * @return array{monto_base: float, monto_descuento: float, monto_final: float, discount_percentage: float}
     */
    public function calculate(float $montoBase, ?int $healthInsuranceId = null): array
    {
        $discountPercentage = 0.0;

        if ($healthInsuranceId) {
            $insurance = HealthInsurance::where('id', $healthInsuranceId)
                ->where('status', 1)
                ->first();

            if ($insurance) {
                $discountPercentage = (float) $insurance->discount_percentage;
            }
        }

        // Cálculo de descuento y monto final
        $montoDescuento = round(($montoBase * $discountPercentage) / 100, 2);
        $montoFinal = round(max(0, $montoBase - $montoDescuento), 2);

        return [
            'monto_base'          => round($montoBase, 2),
            'monto_descuento'     => $montoDescuento,
            'monto_final'         => $montoFinal,
            'discount_percentage' => $discountPercentage,
        ];
    }
}