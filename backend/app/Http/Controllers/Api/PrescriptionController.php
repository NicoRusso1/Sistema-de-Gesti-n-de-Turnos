<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentIssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\PrescriptionService;
use App\Support\Documents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * GT-219: finalizar la consulta y emitir la receta digital del turno.
 */
class PrescriptionController extends Controller
{
    public function __construct(private PrescriptionService $prescriptions)
    {
    }

    #[OA\Patch(
        path: '/api/appointments/{id}/complete',
        summary: 'Finalizar la consulta (Médico asignado)',
        description: 'Marca el turno como "completado". Es el paso previo para emitir la receta. No se puede finalizar un turno cancelado ni uno de un día futuro.',
        tags: ['Recetas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del turno')],
        responses: [
            new OA\Response(response: 200, description: 'Turno finalizado.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Consulta finalizada'),
                new OA\Property(property: 'status', type: 'string', example: 'completado'),
            ])),
            new OA\Response(response: 403, description: 'El usuario no es médico o no es el médico asignado.'),
            new OA\Response(response: 404, description: 'Turno no encontrado.'),
            new OA\Response(response: 422, description: 'El turno está cancelado, ya finalizado o es de un día futuro.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
        ]
    )]
    public function complete(Request $request, $id): JsonResponse
    {
        $appointment = Appointment::find($id);

        if ($error = $this->ensureAssignedDoctor($request->user(), $appointment)) {
            return $error;
        }

        if ($appointment->status === Appointment::STATUS_COMPLETED) {
            return response()->json(['message' => 'El turno ya está finalizado'], 422);
        }

        if (!in_array($appointment->status, Appointment::ACTIVE_STATUSES, true)) {
            return response()->json(['message' => 'Solo se puede finalizar un turno activo'], 422);
        }

        if ($appointment->scheduled_at->isAfter(now()->endOfDay())) {
            return response()->json(['message' => 'No se puede finalizar un turno de un día futuro'], 422);
        }

        $appointment->update(['status' => Appointment::STATUS_COMPLETED]);

        return response()->json([
            'message' => 'Consulta finalizada',
            'status'  => $appointment->status,
        ], 200);
    }

    #[OA\Get(
        path: '/api/appointments/{id}/prescriptions',
        summary: 'Listar las recetas del turno',
        description: 'Accesible por el médico asignado, el paciente titular o un administrador.',
        tags: ['Recetas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del turno')],
        responses: [
            new OA\Response(response: 200, description: 'Recetas del turno.', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Receta'))),
            new OA\Response(response: 403, description: 'Sin permiso sobre el turno.'),
            new OA\Response(response: 404, description: 'Turno no encontrado.'),
        ]
    )]
    public function index(Request $request, $id): JsonResponse
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        if (!$this->canView($request->user(), $appointment)) {
            return response()->json(['message' => 'No tenés permiso para ver las recetas de este turno'], 403);
        }

        return response()->json($appointment->prescriptions()->latest('id')->get(), 200);
    }

    #[OA\Post(
        path: '/api/appointments/{id}/prescriptions',
        summary: 'Emitir receta digital (Médico asignado)',
        description: 'Solo el médico asignado y con el turno finalizado. Guarda el contenido y genera el PDF. Si la generación falla devuelve 502 con la receta guardada para reintentar.',
        tags: ['Recetas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID del turno')],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['content'], properties: [
            new OA\Property(property: 'content', type: 'string', example: "Amoxicilina 500 mg\n1 comprimido cada 8 hs durante 7 días."),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Receta emitida.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Receta emitida'),
                new OA\Property(property: 'prescription', ref: '#/components/schemas/Receta'),
            ])),
            new OA\Response(response: 403, description: 'El usuario no es médico o no es el médico asignado.'),
            new OA\Response(response: 404, description: 'Turno no encontrado.'),
            new OA\Response(response: 422, description: 'Falta el contenido o el turno no está finalizado.'),
            new OA\Response(response: 502, description: 'No se pudo generar el PDF. La receta queda guardada como "fallida" para reintentar.'),
        ]
    )]
    public function store(Request $request, $id): JsonResponse
    {
        $appointment = Appointment::find($id);

        if ($error = $this->ensureAssignedDoctor($request->user(), $appointment)) {
            return $error;
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ], [
            'content.required' => 'El contenido de la receta es obligatorio.',
        ]);

        if ($appointment->status !== Appointment::STATUS_COMPLETED) {
            return response()->json(['message' => 'Primero tenés que finalizar la consulta para emitir la receta'], 422);
        }

        $prescription = $this->prescriptions->issue($appointment, $request->user(), $validated['content']);

        return $this->issueResponse($prescription, 'Receta emitida', 201);
    }

    #[OA\Post(
        path: '/api/prescriptions/{id}/retry',
        summary: 'Reintentar la generación de una receta (Médico emisor)',
        description: 'Vuelve a generar el PDF con el contenido ya guardado.',
        tags: ['Recetas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID de la receta')],
        responses: [
            new OA\Response(response: 200, description: 'Receta generada (o ya estaba emitida).'),
            new OA\Response(response: 403, description: 'Solo el médico que la emitió puede reintentar.'),
            new OA\Response(response: 404, description: 'Receta no encontrada.'),
            new OA\Response(response: 502, description: 'Volvió a fallar.'),
        ]
    )]
    public function retry(Request $request, $id): JsonResponse
    {
        $prescription = Prescription::find($id);

        if (!$prescription) {
            return response()->json(['message' => 'Receta no encontrada'], 404);
        }

        $user = $request->user();
        if (!$user->isDoctor() || (int) $prescription->doctor_id !== (int) $user->id) {
            return response()->json(['message' => 'Solo el médico que emitió la receta puede reintentar'], 403);
        }

        if ($prescription->estado_emision === DocumentIssueStatus::Issued && Documents::exists($prescription->file_url)) {
            return response()->json(['message' => 'La receta ya estaba emitida', 'prescription' => $prescription], 200);
        }

        return $this->issueResponse($this->prescriptions->generate($prescription), 'Receta emitida', 200);
    }

    #[OA\Get(
        path: '/api/prescriptions/{id}/view',
        summary: 'Ver la receta en PDF',
        description: 'Devuelve el PDF para mostrarlo en el navegador. Médico asignado, paciente titular o administrador.',
        tags: ['Recetas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID de la receta')],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF.'),
            new OA\Response(response: 403, description: 'Sin permiso.'),
            new OA\Response(response: 404, description: 'Receta inexistente o sin PDF generado.'),
        ]
    )]
    public function view(Request $request, $id)
    {
        return $this->file($request, $id, inline: true);
    }

    #[OA\Get(
        path: '/api/prescriptions/{id}/download',
        summary: 'Descargar la receta en PDF',
        description: 'Médico asignado, paciente titular o administrador.',
        tags: ['Recetas'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), description: 'ID de la receta')],
        responses: [
            new OA\Response(response: 200, description: 'Archivo PDF.'),
            new OA\Response(response: 403, description: 'Sin permiso.'),
            new OA\Response(response: 404, description: 'Receta inexistente o sin PDF generado.'),
        ]
    )]
    public function download(Request $request, $id)
    {
        return $this->file($request, $id, inline: false);
    }

    private function file(Request $request, $id, bool $inline)
    {
        $prescription = Prescription::with('appointment')->find($id);

        if (!$prescription) {
            return response()->json(['message' => 'Receta no encontrada'], 404);
        }

        if (!$this->canView($request->user(), $prescription->appointment)) {
            return response()->json(['message' => 'No tenés permiso para ver esta receta'], 403);
        }

        $disk = Documents::locate($prescription->file_url);
        if (!$disk) {
            return response()->json(['message' => 'La receta todavía no tiene un PDF generado'], 404);
        }

        $name = "Receta_{$prescription->id}_Turno_{$prescription->appointment_id}.pdf";

        return $inline
            ? $disk->response($prescription->file_url, $name, ['Content-Type' => 'application/pdf'])
            : $disk->download($prescription->file_url, $name);
    }

    private function issueResponse(Prescription $prescription, string $message, int $status): JsonResponse
    {
        if ($prescription->estado_emision === DocumentIssueStatus::Failed) {
            return response()->json([
                'message'      => 'No se pudo generar la receta. El contenido quedó guardado, podés reintentar.',
                'error'        => $prescription->error_emision,
                'prescription' => $prescription,
            ], 502);
        }

        return response()->json(['message' => $message, 'prescription' => $prescription], $status);
    }

    private function ensureAssignedDoctor(Patient $user, ?Appointment $appointment): ?JsonResponse
    {
        if (!$user->isDoctor()) {
            return response()->json(['message' => 'Solo los médicos pueden usar esta función'], 403);
        }

        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        if ((int) $appointment->doctor_id !== (int) $user->id) {
            return response()->json(['message' => 'Solo el médico asignado al turno puede hacerlo'], 403);
        }

        return null;
    }

    private function canView(Patient $user, Appointment $appointment): bool
    {
        return (int) $appointment->patient_id === (int) $user->id
            || (int) $appointment->doctor_id === (int) $user->id
            || $user->hasAdminAccess();
    }
}
