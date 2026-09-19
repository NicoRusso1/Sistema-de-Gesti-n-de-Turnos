<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function index()
    {
        $datos = Specialty::all();

        return response()->json($datos, 200);
    }

    public function store(Request $request)
    {
        $specialty = Specialty::create($request->all());

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

        $specialty->update($request->all());

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