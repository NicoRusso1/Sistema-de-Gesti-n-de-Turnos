<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\HealthInsurance;

class PaymentCalculationService
{
    /**
     * Calcula los montos con descuento de obra social para un paciente y un turno.
     *
     * @param float $montoBase Valor base de la consulta
     * @param Patient $patient Paciente titular del turno
     * @return array{
     *     monto_original: float,
     *     porcentaje_descuento: float,
     *     descuento_aplicado: float,
     *     monto_final: float,
     *     tiene_obra_social: bool,
     *     obra_social_nombre: ?string,
     *     afiliado_numero: ?string
     * }
     */
    public function calcularMonto(float $montoBase, Patient $patient): array
    {
        $montoBaseNormalizado = round(max(0, $montoBase), 2);
        $descuentoPorcentaje = 0.0;
        $nombreObraSocial = null;
        $afiliadoNumero = null;
        $obraSocialValida = false;

        // Cargar relación de obra social del paciente
        $cobertura = $patient->healthInsuranceRecord()
            ->with('healthInsurance')
            ->first();

        if ($cobertura && $cobertura->healthInsurance) {
            $obraSocial = $cobertura->healthInsurance;

            // Criterio 1: Validar si la obra social está activa/vigente (status == 1)
            if ((int) $obraSocial->status === 1) {
                $descuentoPorcentaje = (float) $obraSocial->discount_percentage;
                $nombreObraSocial = $obraSocial->name;
                $afiliadoNumero = $cobertura->member_number;
                $obraSocialValida = true;
            }
        }

        // Criterio 2: Cálculo del descuento correspondiente
        $descuentoAplicado = round(($montoBaseNormalizado * $descuentoPorcentaje) / 100, 2);

        // Criterio 3 y 4: Monto final a abonar (o monto total si no aplica obra social)
        $montoFinal = round(max(0, $montoBaseNormalizado - $descuentoAplicado), 2);

        return [
            'monto_original'       => $montoBaseNormalizado,
            'porcentaje_descuento' => $descuentoPorcentaje,
            'descuento_aplicado'   => $descuentoAplicado,
            'monto_final'          => $montoFinal,
            'tiene_obra_social'    => $obraSocialValida,
            'obra_social_nombre'   => $nombreObraSocial,
            'afiliado_numero'      => $afiliadoNumero,
        ];
    }
}