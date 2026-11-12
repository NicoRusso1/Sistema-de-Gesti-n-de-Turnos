<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Support\Documents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    #[OA\Get(
        path: '/api/certificados',
        summary: 'Consultar certificados emitidos del paciente autenticado (GT-153)',
        tags: ['Certificados'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Listado de certificados del paciente ordenados por fecha descendente.'),
            new OA\Response(response: 401, description: 'No autenticado.')
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // GT-153: Scope por usuario (el paciente solo accede a certificados de sus propios turnos)
        $query = Certificate::with([
            'appointment.doctor',
            'appointment.specialty',
        ])
        ->whereHas('appointment', function ($q) use ($user) {
            // Si es un admin global puede consultar todos, pero para pacientes aplica su propio ID
            if (!$user->hasAdminAccess()) {
                $q->where('patient_id', $user->id);
            }
        })
        // Orden descendente por fecha de emisión
        ->orderBy('issue_date', 'desc');

        $certificados = $query->get()->map(function (Certificate $cert) {
            $appointment = $cert->appointment;
            
            // Criterio 4: Marcar como no válidos los certificados de turnos cancelados
            $esCancelado = in_array($appointment->status, ['cancelado', 'cancelled'], true);
            $esValido = !$esCancelado && !empty($cert->certificate_url);

            return [
                'id'                => $cert->id,
                'turno_id'          => $cert->appointment_id,
                'fecha_emision'     => $cert->issue_date?->toIso8601String(),
                'fecha_emision_fmt' => $cert->issue_date?->format('d/m/Y H:i'),
                'medico'            => [
                    'nombre'       => $appointment->doctor ? "{$appointment->doctor->first_name} {$appointment->doctor->last_name}" : 'No asignado',
                    'especialidad' => $appointment->specialty?->name ?? 'General',
                ],
                'estado_turno'      => $appointment->status,
                'valido'            => $esValido,
                'motivo_invalidez'  => $esCancelado ? 'El turno fue cancelado con posterioridad a la emisión' : null,
                'tiene_archivo'     => !empty($cert->certificate_url),
            ];
        });

        return response()->json([
            'data'  => $certificados,
            'total' => $certificados->count(),
        ], 200);
    }

    #[OA\Get(
        path: '/api/certificados/{id}/download',
        summary: 'Descarga segura del certificado médico (GT-154)',
        tags: ['Certificados'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del certificado')
        ],
        responses: [
            new OA\Response(response: 200, description: 'Descarga del archivo PDF.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'No tenés permiso para descargar este certificado.'),
            new OA\Response(response: 404, description: 'Certificado o archivo físico no encontrado.')
        ]
    )]
    public function download(Request $request, int $id)
    {
        $user = $request->user();
        $certificate = Certificate::with('appointment')->find($id);

        if (!$certificate) {
            return response()->json(['message' => 'Certificado no encontrado.'], 404);
        }

        $appointment = $certificate->appointment;

        // GT-154: Validación estricta de propiedad (solo el paciente titular, el médico asignado o admin)
        $esTitular = (int) $appointment->patient_id === (int) $user->id;
        $esMedico = (int) $appointment->doctor_id === (int) $user->id;
        $esAdmin = $user->hasAdminAccess();

        if (!$esTitular && !$esMedico && !$esAdmin) {
            return response()->json(['message' => 'No tenés permiso para descargar este certificado.'], 403);
        }

        $path = $certificate->certificate_url;
        if (!$path) {
            return response()->json(['message' => 'Este certificado no tiene un archivo PDF generado.'], 404);
        }

        $disk = Documents::locate($path);
        if (!$disk) {
            return response()->json(['message' => 'El archivo físico no fue encontrado en el servidor.'], 404);
        }

        $nombreDescarga = "Certificado_Turno_{$appointment->id}_{$certificate->id}.pdf";

        return $disk->download($path, $nombreDescarga, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}