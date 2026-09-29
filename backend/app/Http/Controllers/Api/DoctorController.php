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
            $query->inactive();
        } else {
            $query->active();
        }

        return response()->json($query->paginate(15), 200);
    }

    public function destroy(string $id)
    {
        $doctor = Patient::doctors()->active()->find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado'
            ], 404);
        }

        $doctor->status = 0;
        $doctor->save();
        $doctor->tokens()->delete();

        return response()->json([
            'message' => 'Médico dado de baja correctamente'
        ], 200);
    }

    public function restore(string $id)
    {
        $doctor = Patient::doctors()->inactive()->find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico dado de baja no encontrado'
            ], 404);
        }

        $doctor->status = 1;
        $doctor->save();

        return response()->json([
            'message' => 'Médico reactivado correctamente'
        ], 200);
    }
}
