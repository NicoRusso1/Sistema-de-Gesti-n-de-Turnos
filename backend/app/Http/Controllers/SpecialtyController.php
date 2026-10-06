<?php
namespace App\Http\Controllers;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
class SpecialtyController extends Controller
{
    #[OA\Get(
        path: '/api/specialties',
        summary: 'Listar especialidades (público: lo usa el flujo de reserva de turnos)',
        tags: ['Especialidades'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de especialidades.')
        ]
    )]
    public function index(Request $request)
    {
        $query = Specialty::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }
        return response()->json($query->orderBy('name')->get(), 200);
    }
    #[OA\Post(
        path: '/api/specialties',
        summary: 'Crear una especialidad',
        tags: ['Especialidades'],
        security: [
            ['sanctum' => []]
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'description'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Traumatología'),
                    new OA\Property(property: 'description', type: 'string', example: 'Lesiones de huesos y articulaciones')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Especialidad creada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 422, description: 'Datos inválidos o nombre repetido.')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('specialties', 'name')],
            'description' => ['required', 'string', 'max:255'],
        ], $this->messages());
        $specialty = Specialty::create($validated);
        return response()->json([
            'message' => 'Especialidad creada correctamente',
            'specialty' => $specialty
        ], 201);
    }
    #[OA\Get(
        path: '/api/specialties/{id}',
        summary: 'Ver una especialidad',
        tags: ['Especialidades'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Especialidad encontrada.'),
            new OA\Response(response: 404, description: 'Especialidad no encontrada.')
        ]
    )]
    public function show(string $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada'
            ], 404);
        }
        return response()->json($specialty, 200);
    }
    #[OA\Put(
        path: '/api/specialties/{id}',
        summary: 'Modificar una especialidad',
        tags: ['Especialidades'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Traumatología'),
                    new OA\Property(property: 'description', type: 'string', example: 'Lesiones de huesos y articulaciones')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Especialidad actualizada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Especialidad no encontrada.'),
            new OA\Response(response: 422, description: 'Datos inválidos o nombre repetido.')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada'
            ], 404);
        }
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('specialties', 'name')->ignore($specialty->id),
            ],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
        ], $this->messages());
        $specialty->update($validated);
        return response()->json([
            'message' => 'Especialidad actualizada correctamente',
            'specialty' => $specialty->fresh()
        ], 200);
    }
    #[OA\Delete(
        path: '/api/specialties/{id}',
        summary: 'Eliminar una especialidad (solo si no tiene médicos ni turnos asociados)',
        tags: ['Especialidades'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Especialidad eliminada.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permisos de administrador.'),
            new OA\Response(response: 404, description: 'Especialidad no encontrada.'),
            new OA\Response(response: 409, description: 'Tiene médicos o turnos asociados.')
        ]
    )]
    public function destroy(string $id)
    {
        $specialty = Specialty::find($id);
        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada'
            ], 404);
        }
        $doctors = $specialty->doctors()->count();
        $appointments = $specialty->appointments()->count();
        // Integridad referencial: no se borra si hay médicos o turnos que dependen de ella
        if ($doctors > 0 || $appointments > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la especialidad porque tiene médicos o turnos asociados',
                'doctors_count' => $doctors,
                'appointments_count' => $appointments,
            ], 409);
        }
        $specialty->delete();
        return response()->json([
            'message' => 'Especialidad eliminada correctamente'
        ], 200);
    }
    private function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio',
            'name.unique' => 'Ya existe una especialidad con ese nombre',
            'name.max' => 'El nombre no puede superar los 255 caracteres',
            'description.required' => 'La descripción es obligatoria',
            'description.max' => 'La descripción no puede superar los 255 caracteres',
        ];
    }
}