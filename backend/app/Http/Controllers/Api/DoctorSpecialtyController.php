<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Specialty;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
class DoctorSpecialtyController extends Controller
{
    #[OA\Get(
        path: '/api/doctors/{id}/specialties',
        summary: 'Listar las especialidades asignadas a un médico',
        tags: ['Especialidades de médicos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Especialidades del médico.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'El usuario no es médico.')
        ]
    )]
    public function index(string $id)
    {
        $doctor = $this->doctorOrError($id);
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }
        return response()->json($this->specialtiesOf($doctor), 200);
    }
    #[OA\Post(
        path: '/api/doctors/{id}/specialties',
        summary: 'Asignar una especialidad a un médico',
        tags: ['Especialidades de médicos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['specialty_id'],
                properties: [
                    new OA\Property(property: 'specialty_id', type: 'integer', example: 1)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Especialidad asignada.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 409, description: 'El médico ya tiene esa especialidad.'),
            new OA\Response(response: 422, description: 'El usuario no es médico o la especialidad no existe.')
        ]
    )]
    public function store(Request $request, string $id)
    {
        $doctor = $this->doctorOrError($id);
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }
        $validated = $request->validate([
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
        ], $this->messages());
        $duplicated = response()->json([
            'message' => 'El médico ya tiene asignada esa especialidad'
        ], 409);
        if ($doctor->specialties()->where('specialties.id', $validated['specialty_id'])->exists()) {
            return $duplicated;
        }
        // El índice UNIQUE de la tabla cubre el caso de dos pedidos simultáneos
        try {
            $doctor->specialties()->attach($validated['specialty_id']);
        } catch (UniqueConstraintViolationException) {
            return $duplicated;
        }
        return response()->json([
            'message' => 'Especialidad asignada correctamente',
            'specialties' => $this->specialtiesOf($doctor)
        ], 201);
    }
    #[OA\Put(
        path: '/api/doctors/{id}/specialties',
        summary: 'Reemplazar todas las especialidades de un médico (multiselector)',
        tags: ['Especialidades de médicos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['specialty_ids'],
                properties: [
                    new OA\Property(
                        property: 'specialty_ids',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Especialidades actualizadas.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado.'),
            new OA\Response(response: 422, description: 'No es médico, hay ids repetidos o inexistentes.')
        ]
    )]
    public function sync(Request $request, string $id)
    {
        $doctor = $this->doctorOrError($id);
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }
        $validated = $request->validate([
            'specialty_ids' => ['present', 'array'],
            'specialty_ids.*' => ['integer', 'distinct', 'exists:specialties,id'],
        ], $this->messages());
        $doctor->specialties()->sync($validated['specialty_ids']);
        return response()->json([
            'message' => 'Especialidades actualizadas correctamente',
            'specialties' => $this->specialtiesOf($doctor)
        ], 200);
    }
    #[OA\Delete(
        path: '/api/doctors/{id}/specialties/{specialtyId}',
        summary: 'Desasignar una especialidad de un médico',
        tags: ['Especialidades de médicos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'specialtyId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Especialidad desasignada.'),
            new OA\Response(response: 404, description: 'Usuario no encontrado o especialidad no asignada.'),
            new OA\Response(response: 422, description: 'El usuario no es médico.')
        ]
    )]
    public function destroy(string $id, string $specialtyId)
    {
        $doctor = $this->doctorOrError($id);
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }
        if ($doctor->specialties()->detach($specialtyId) === 0) {
            return response()->json([
                'message' => 'El médico no tiene asignada esa especialidad'
            ], 404);
        }
        return response()->json([
            'message' => 'Especialidad desasignada correctamente',
            'specialties' => $this->specialtiesOf($doctor)
        ], 200);
    }
    #[OA\Get(
        path: '/api/specialties/{id}/doctors',
        summary: 'Médicos activos de una especialidad (filtro del flujo de reserva)',
        tags: ['Especialidades de médicos'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Médicos asignados a la especialidad.'),
            new OA\Response(response: 404, description: 'Especialidad no encontrada.')
        ]
    )]
    public function doctorsBySpecialty(string $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada'
            ], 404);
        }
        // Solo médicos activos que tengan asignada esta especialidad
        $doctors = Patient::doctors()
            ->active()
            ->whereHas('specialties', fn($q) => $q->where('specialties.id', $specialty->id))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);
        return response()->json($doctors, 200);
    }
    // Devuelve el médico, o la respuesta de error si no existe o no es de tipo médico
    private function doctorOrError(string $id): Patient|JsonResponse
    {
        $user = Patient::with('userType')->find($id);
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }
        if (!$user->isDoctor()) {
            return response()->json([
                'message' => 'Solo se pueden asignar especialidades a usuarios de tipo médico'
            ], 422);
        }
        return $user;
    }
    private function specialtiesOf(Patient $doctor)
    {
        return $doctor->specialties()->orderBy('name')->get()->makeHidden('pivot');
    }
    private function messages(): array
    {
        return [
            'specialty_id.required' => 'La especialidad es obligatoria',
            'specialty_id.exists' => 'La especialidad no existe',
            'specialty_ids.present' => 'Enviá la lista de especialidades',
            'specialty_ids.*.distinct' => 'No se puede repetir la misma especialidad',
            'specialty_ids.*.exists' => 'Alguna de las especialidades no existe',
        ];
    }
}