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
#[OA\Schema(
    schema: 'RecetaHistorial',
    description: 'GT-220: ítem del historial de recetas.',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'turno_id', type: 'integer', example: 12),
        new OA\Property(property: 'fecha_turno', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'fecha_emision', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'medico', type: 'string', example: 'Ana Perez'),
        new OA\Property(property: 'paciente', type: 'string', nullable: true, example: 'Lucia Gomez'),
        new OA\Property(property: 'especialidad', type: 'string', example: 'Clínica Médica'),
        new OA\Property(property: 'contenido', type: 'string', example: "Ibuprofeno 400 mg\n1 comprimido cada 8 hs."),
        new OA\Property(property: 'estado_emision', type: 'string', enum: ['pendiente', 'emitida', 'fallida']),
        new OA\Property(property: 'tiene_archivo', type: 'boolean', example: true),
    ]
)]
class PrescriptionSchemas
{
}
