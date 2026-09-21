<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Lista los turnos visibles para el usuario logueado, según su rol
     * y permisos (ver Appointment::scopeVisibleFor).
     */
    public function index(Request $request): JsonResponse
    {
        $appointments = Appointment::query()
            ->visibleFor($request->user())
            ->with(['patient:id,first_name,last_name', 'doctor:id,first_name,last_name', 'room', 'state'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json($appointments);
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $start = $request->validated('start_time');
        $end = \Carbon\Carbon::createFromFormat('H:i', $start)->addMinutes(15)->format('H:i');

        $reservedState = AppointmentState::where('name', 'reserved')->firstOrFail();

        $appointment = Appointment::create([
            'patient_id' => $request->validated('patient_id'),
            'doctor_id' => $request->validated('doctor_id'),
            'room_id' => $request->validated('room_id'),
            'date' => $request->validated('date'),
            'start_time' => $start,
            'end_time' => $end,
            'duration' => 15,
            'state_id' => $reservedState->id,
        ]);

        return response()->json([
            'message' => 'Turno reservado correctamente',
            'appointment' => $appointment->load(['patient', 'doctor', 'room', 'state']),
        ], 201);
    }
}
