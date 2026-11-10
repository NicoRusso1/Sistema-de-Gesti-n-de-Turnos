<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Receta',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'appointment_id', type: 'integer', example: 12),
        new OA\Property(property: 'doctor_id', type: 'integer', example: 4),
        new OA\Property(property: 'patient_id', type: 'integer', example: 9),
        new OA\Property(property: 'content', type: 'string', example: "Ibuprofeno 400 mg\n1 comprimido cada 8 hs."),
        new OA\Property(property: 'issue_date', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'file_url', type: 'string', nullable: true, example: 'recetas/receta_3_1793279024.pdf'),
        new OA\Property(property: 'estado_emision', type: 'string', enum: ['pendiente', 'emitida', 'fallida']),
        new OA\Property(property: 'error_emision', type: 'string', nullable: true),
    ]
)]
class PrescriptionSchemas
{
}
