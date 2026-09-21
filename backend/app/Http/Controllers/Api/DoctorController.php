<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::doctors();

        if ($request->boolean('trashed')) {
            $query->onlyTrashed();
        }

        return response()->json($query->paginate(15), 200);
    }

    public function destroy(string $id)
    {
        $doctor = Patient::doctors()->find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado'
            ], 404);
        }

        $doctor->tokens()->delete();
        $doctor->delete();

        return response()->json([
            'message' => 'Médico eliminado correctamente'
        ], 200);
    }

    public function restore(string $id)
    {
        $doctor = Patient::doctors()->onlyTrashed()->find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico eliminado no encontrado'
            ], 404);
        }

        $doctor->restore();

        return response()->json([
            'message' => 'Médico restaurado correctamente'
        ], 200);
    }
}
