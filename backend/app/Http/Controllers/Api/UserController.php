<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::with(['role', 'userType', 'personalData']);

        // Filtro por Rol
        if ($request->filled('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filtro por Tipo de Usuario
        if ($request->filled('user_type')) {
            $query->whereHas('userType', function ($q) use ($request) {
                $q->where('name', $request->user_type);
            });
        }

        // Filtro por Estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro de Texto Libre Inteligente (Nombre, Apellido, Email o DNI)
        if ($request->filled('search')) {
            $search = $request->search;
            
            $query->where(function ($q) use ($search) {
                // Separamos por espacios para permitir búsquedas como "Juan Perez"
                $terms = explode(' ', $search);
                
                foreach ($terms as $term) {
                    $q->where(function ($subQuery) use ($term) {
                        $subQuery->where('first_name', 'like', "%{$term}%")
                                 ->orWhere('last_name', 'like', "%{$term}%")
                                 ->orWhere('email', 'like', "%{$term}%")
                                 ->orWhereHas('personalData', function ($qData) use ($term) {
                                     $qData->where('national_id', 'like', "%{$term}%");
                                 });
                    });
                }
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(15);

        return response()->json($users);
    }
}