<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SpecialtyController extends Controller
{
    public function index()
    {
        $datos = Specialty::all();

        return response()->json($datos, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('specialties', 'name')],
            'description' => 'nullable|string|max:255',
        ]);

        $specialty = Specialty::create($validated);

        return response()->json([
            'message' => 'Especialidad creada correctamente',
            'specialty' => $specialty
        ], 201);
    }

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

    public function update(Request $request, string $id)
    {
        $specialty = Specialty::find($id);

        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada'
            ], 404);
        }

        $validated = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255',
                Rule::unique('specialties', 'name')->ignore($id)],
            'description' => 'nullable|string|max:255',
        ]);

        $specialty->update($validated);

        return response()->json([
            'message' => 'Especialidad actualizada correctamente',
            'specialty' => $specialty->fresh()
        ], 200);
    }

    public function destroy(string $id)
    {
        $specialty = Specialty::find($id);

        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada'
            ], 404);
        }

        $specialty->delete();

        return response()->json([
            'message' => 'Especialidad eliminada correctamente'
        ], 200);
    }
}
