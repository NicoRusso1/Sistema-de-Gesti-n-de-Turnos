<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sala;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SalaController extends Controller
{
    /**
     * Listar todas las salas con filtros opcionales
     */
    public function index(Request $request)
    {
        $query = Sala::with('specialty');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('piso')) {
            $query->where('piso', $request->piso);
        }

        if ($request->filled('especialidad_id')) {
            $query->where('especialidad_id', $request->especialidad_id);
        }

        if ($request->filled('search')) {
            $query->where('numero', 'like', "%{$request->search}%");
        }

        return response()->json($query->orderBy('piso')->orderBy('numero')->get(), 200);
    }

    /**
     * Crear una nueva sala
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'numero' => [
                'required',
                'string',
                'max:50',
                Rule::unique('salas', 'numero')->where(fn ($query) => 
                    $query->where('piso', $request->input('piso'))
                ),
            ],
            'piso' => ['required', 'integer', 'min:0', 'max:100'],
            'especialidad_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'estado' => ['sometimes', 'in:disponible,fuera_de_servicio'],
        ], [
            'numero.unique' => 'Ya existe una sala con este número en el piso seleccionado.',
            'especialidad_id.exists' => 'La especialidad seleccionada no es válida.',
        ]);

        $sala = Sala::create($validated);

        return response()->json([
            'message' => 'Sala creada exitosamente.',
            'sala' => $sala->load('specialty')
        ], 201);
    }

    /**
     * Ver detalles de una sala
     */
    public function show(string $id)
    {
        $sala = Sala::with('specialty')->find($id);

        if (!$sala) {
            return response()->json(['message' => 'Sala no encontrada.'], 404);
        }

        return response()->json($sala, 200);
    }

    /**
     * Actualizar una sala existente
     */
    public function update(Request $request, string $id)
    {
        $sala = Sala::find($id);

        if (!$sala) {
            return response()->json(['message' => 'Sala no encontrada.'], 404);
        }

        $pisoTarget = $request->input('piso', $sala->piso);

        $validated = $request->validate([
            'numero' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('salas', 'numero')
                    ->where(fn ($query) => $query->where('piso', $pisoTarget))
                    ->ignore($sala->id),
            ],
            'piso' => ['sometimes', 'required', 'integer', 'min:0', 'max:100'],
            'especialidad_id' => ['nullable', 'integer', 'exists:specialties,id'],
            'estado' => ['sometimes', 'in:disponible,fuera_de_servicio'],
        ], [
            'numero.unique' => 'Ya existe otra sala con este número en el piso especificado.',
            'especialidad_id.exists' => 'La especialidad seleccionada no es válida.',
        ]);

        $sala->update($validated);

        return response()->json([
            'message' => 'Sala actualizada exitosamente.',
            'sala' => $sala->fresh()->load('specialty')
        ], 200);
    }

    /**
     * Eliminar físicamente o aplicar baja lógica si tiene turnos asociados
     */
    public function destroy(string $id)
    {
        $sala = Sala::find($id);

        if (!$sala) {
            return response()->json(['message' => 'Sala no encontrada.'], 404);
        }

        // Comprobación de turnos asociados si existen las tablas correspondientes
        $tieneTurnos = false;
        if (Schema::hasTable('turnos')) {
            $tieneTurnos = DB::table('turnos')->where('sala_id', $id)->exists();
        } elseif (Schema::hasTable('appointments')) {
            $tieneTurnos = DB::table('appointments')->where('sala_id', $id)->exists();
        }

        if ($tieneTurnos) {
            return response()->json([
                'message' => 'No es posible eliminar físicamente la sala porque tiene turnos asociados. Puede cambiar su estado a "fuera_de_servicio".'
            ], 422);
        }

        $sala->delete();

        return response()->json(['message' => 'Sala eliminada correctamente.'], 200);
    }

    /**
     * Alternar estado entre 'disponible' y 'fuera_de_servicio'
     */
    public function cambiarEstado(Request $request, string $id)
    {
        $sala = Sala::find($id);

        if (!$sala) {
            return response()->json(['message' => 'Sala no encontrada.'], 404);
        }

        $validated = $request->validate([
            'estado' => ['required', 'in:disponible,fuera_de_servicio'],
        ]);

        $sala->estado = $validated['estado'];
        $sala->save();

        return response()->json([
            'message' => 'Estado de la sala actualizado correctamente.',
            'sala' => $sala
        ], 200);
    }

    /**
     * Consulta de disponibilidad de salas con filtros
     * GET /api/salas/disponibilidad?fecha=YYYY-MM-DD&franja_horaria=08:00-12:00&especialidad_id=1
     */
    public function disponibilidad(Request $request)
    {
        $request->validate([
            'fecha' => ['nullable', 'date'],
            'franja_horaria' => ['nullable', 'string'],
            'especialidad_id' => ['nullable', 'integer', 'exists:specialties,id'],
        ]);

        $query = Sala::with('specialty');

        // Regla 2: Excluir salas fuera de servicio
        $query->where('estado', '!=', 'fuera_de_servicio');

        if ($request->filled('especialidad_id')) {
            $query->where('especialidad_id', $request->especialidad_id);
        }

        $salas = $query->orderBy('piso')->orderBy('numero')->get();

        $fecha = $request->input('fecha');
        $franja = $request->input('franja_horaria');

        // Mapear cada sala con su estado de ocupación
        $resultado = $salas->map(function ($sala) use ($fecha, $franja) {
            $turnoOcupante = null;
            $ocupada = false;

            // Si existe la tabla de turnos, verificar ocupación
            if ($fecha && Schema::hasTable('turnos')) {
                $turnoQuery = DB::table('turnos')
                    ->where('sala_id', $sala->id)
                    ->whereDate('fecha', $fecha);

                if ($franja) {
                    $turnoQuery->where('franja_horaria', $franja);
                }

                $turnoOcupante = $turnoQuery->first();
                $ocupada = $turnoOcupante !== null;
            }

            return [
                'id' => $sala->id,
                'numero' => $sala->numero,
                'piso' => $sala->piso,
                'especialidad' => $sala->specialty ? $sala->specialty->name : 'General',
                'estado_base' => $sala->estado,
                'ocupacion' => $ocupada ? 'ocupada' : 'disponible',
                'turno_asociado' => $turnoOcupante ? [
                    'id' => $turnoOcupante->id,
                    'detalle' => $turnoOcupante->paciente ?? 'Turno reservado',
                ] : null,
            ];
        });

        return response()->json($resultado, 200);
    }
}