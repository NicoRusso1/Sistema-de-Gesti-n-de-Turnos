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
     * Consulta optimizada de disponibilidad de salas (GT-162 y GT-164)
     * Resuelve el problema N+1 y valida solapamiento horario real.
     */
    public function disponibilidad(Request $request)
    {
        $request->validate([
            'fecha' => ['nullable', 'date'],
            'franja_horaria' => ['nullable', 'string'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'especialidad_id' => ['nullable', 'integer', 'exists:specialties,id'],
        ]);

        $query = Sala::with('specialty')
            // Regla: Excluir salas fuera de servicio
            ->where('estado', '!=', 'fuera_de_servicio');

        if ($request->filled('especialidad_id')) {
            $query->where('especialidad_id', $request->especialidad_id);
        }

        $salas = $query->orderBy('piso')->orderBy('numero')->get();

        $fecha = $request->input('fecha');
        $franja = $request->input('franja_horaria');
        $horaInicio = $request->input('hora_inicio');
        $horaFin = $request->input('hora_fin');

        // Si la franja viene como "08:00-12:00", desglosamos hora inicio y fin automáticamente
        if ($franja && str_contains($franja, '-') && !$horaInicio && !$horaFin) {
            $partes = explode('-', $franja);
            $horaInicio = trim($partes[0]);
            $horaFin = trim($partes[1]);
        }

        // OPTIMIZACIÓN GT-162: Pre-cargar en UNA SOLA QUERY todos los turnos ocupados
        $turnosPorSala = collect();

        if ($fecha && $salas->isNotEmpty() && (Schema::hasTable('turnos') || Schema::hasTable('appointments'))) {
            $tablaTurnos = Schema::hasTable('turnos') ? 'turnos' : 'appointments';
            $salaIds = $salas->pluck('id')->toArray();

            $turnosQuery = DB::table($tablaTurnos)
                ->whereIn('sala_id', $salaIds)
                ->whereDate('fecha', $fecha);

            // VALIDACIÓN GT-164: Solapamiento horario
            // Condición: (inicio_turno < fin_consultado) Y (fin_turno > inicio_consultado)
            if ($horaInicio && $horaFin && Schema::hasColumn($tablaTurnos, 'hora_inicio')) {
                $turnosQuery->where(function ($q) use ($horaInicio, $horaFin) {
                    $q->where('hora_inicio', '<', $horaFin)
                      ->where('hora_fin', '>', $horaInicio);
                });
            } elseif ($franja && Schema::hasColumn($tablaTurnos, 'franja_horaria')) {
                // Coincidencia por etiqueta fija
                $turnosQuery->where('franja_horaria', $franja);
            }

            // Excluir turnos cancelados si la columna estado existe en turnos
            if (Schema::hasColumn($tablaTurnos, 'estado')) {
                $turnosQuery->where('estado', '!=', 'cancelado');
            }

            // Agrupamos por sala_id (O(1) en memoria, sin queries adicionales)
            $turnosPorSala = $turnosQuery->get()->keyBy('sala_id');
        }

        // Mapear resultado con estado de solapamiento/ocupación
        $resultado = $salas->map(function ($sala) use ($turnosPorSala) {
            $turno = $turnosPorSala->get($sala->id);
            $estaSolapada = $turno !== null;

            return [
                'id' => $sala->id,
                'numero' => $sala->numero,
                'piso' => $sala->piso,
                'especialidad' => $sala->specialty ? $sala->specialty->name : 'General',
                'estado_base' => $sala->estado,
                'ocupacion' => $estaSolapada ? 'ocupada' : 'disponible',
                'solapada' => $estaSolapada, // Flag explícito para GT-164
                'turno_asociado' => $turno ? [
                    'id' => $turno->id,
                    'detalle' => $turno->paciente ?? ($turno->motivo ?? 'Turno reservado'),
                    'horario' => isset($turno->hora_inicio) ? "{$turno->hora_inicio} - {$turno->hora_fin}" : ($turno->franja_horaria ?? 'N/A'),
                ] : null,
            ];
        });

        return response()->json($resultado, 200);
    }
}