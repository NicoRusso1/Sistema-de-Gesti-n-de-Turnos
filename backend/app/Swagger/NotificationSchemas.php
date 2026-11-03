<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Notificacion',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'patient_id', type: 'integer', example: 3),
        new OA\Property(property: 'appointment_id', type: 'integer', nullable: true, example: 45),
        new OA\Property(property: 'tipo', type: 'string', example: 'recordatorio', description: 'cancelacion, reprogramacion, recordatorio, confirmacion o certificado.'),
        new OA\Property(property: 'titulo', type: 'string', example: 'Tu próximo turno es el 04/11/2026 a las 10:00 hs'),
        new OA\Property(property: 'mensaje', type: 'string', example: 'Recordá que tu próximo turno está agendado para el día 04/11/2026 a las 10:00 hs.'),
        new OA\Property(property: 'leido', type: 'boolean', example: false),
        new OA\Property(property: 'estado_envio', type: 'string', enum: ['pendiente', 'enviada', 'fallida'], example: 'fallida'),
        new OA\Property(property: 'error_envio', type: 'string', nullable: true, example: 'Connection could not be established with host smtp.example.com'),
        new OA\Property(property: 'enviada_at', type: 'string', format: 'date-time', nullable: true, example: null),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-11-03T15:30:00.000000Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-11-03T15:30:01.000000Z'),
        new OA\Property(
            property: 'patient',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 3),
                new OA\Property(property: 'first_name', type: 'string', example: 'Lucía'),
                new OA\Property(property: 'last_name', type: 'string', example: 'Gómez'),
                new OA\Property(property: 'email', type: 'string', example: 'lucia@example.com'),
            ]
        ),
        new OA\Property(
            property: 'appointment',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 45),
                new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time', example: '2026-11-04T10:00:00.000000Z'),
                new OA\Property(property: 'status', type: 'string', example: 'abonado'),
                new OA\Property(
                    property: 'doctor',
                    type: 'object',
                    nullable: true,
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 7),
                        new OA\Property(property: 'first_name', type: 'string', example: 'Martín'),
                        new OA\Property(property: 'last_name', type: 'string', example: 'Pérez'),
                    ]
                ),
                new OA\Property(
                    property: 'specialty',
                    type: 'object',
                    nullable: true,
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 2),
                        new OA\Property(property: 'name', type: 'string', example: 'Cardiología'),
                    ]
                ),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'NotificacionesPaginadas',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Notificacion')),
        new OA\Property(property: 'first_page_url', type: 'string', example: 'http://127.0.0.1:8000/api/notifications?page=1'),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
        new OA\Property(property: 'last_page_url', type: 'string', example: 'http://127.0.0.1:8000/api/notifications?page=3'),
        new OA\Property(
            property: 'links',
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'url', type: 'string', nullable: true),
                    new OA\Property(property: 'label', type: 'string', example: '1'),
                    new OA\Property(property: 'active', type: 'boolean', example: true),
                ]
            )
        ),
        new OA\Property(property: 'next_page_url', type: 'string', nullable: true, example: 'http://127.0.0.1:8000/api/notifications?page=2'),
        new OA\Property(property: 'path', type: 'string', example: 'http://127.0.0.1:8000/api/notifications'),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'prev_page_url', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 38),
    ]
)]
#[OA\Schema(
    schema: 'ErrorValidacion',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'El estado debe ser pendiente, enviada o fallida.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['estado' => ['El estado debe ser pendiente, enviada o fallida.']]
        ),
    ]
)]
class NotificationSchemas
{
}
