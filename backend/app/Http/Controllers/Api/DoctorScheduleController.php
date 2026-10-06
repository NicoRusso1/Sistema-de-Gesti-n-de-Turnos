<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DoctorScheduleController extends Controller
{
    private const OPEN_TIME = '08:00';
    private const CLOSE_TIME = '20:00';
    private const SLOT_MINUTES = 15;
    private const MAX_YEARS = 2;

    #[OA\Get(
        path: '/api/doctors/{id}/schedules',
        summary: 'Listar la agenda semanal de un médico',
        tags: ['Agenda de médicos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del médico', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 3)
        ],
        responses: [
            new OA\Response(response: 200, description: 'Franjas horarias del médico.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permiso para ver agendas.'),
            new OA\Response(response: 404, description: 'Médico no encontrado.')
        ]
    )]
    public function index(string $doctorId)
    {
        $doctor = $this->findDoctor($doctorId);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado'
            ], 404);
        }

        $schedules = DoctorSchedule::where('doctor_id', $doctor->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json($schedules, 200);
    }

    #[OA\Post(
        path: '/api/doctors/{id}/schedules',
        summary: 'Agregar una franja horaria a la agenda de un médico',
        tags: ['Agenda de médicos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del médico', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 3)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['day_of_week', 'start_time', 'end_time'],
                properties: [
                    new OA\Property(property: 'day_of_week', type: 'integer', minimum: 1, maximum: 7, example: 1),
                    new OA\Property(property: 'start_time', type: 'string', example: '09:00'),
                    new OA\Property(property: 'end_time', type: 'string', example: '13:00')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Franja creada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permiso para editar agendas.'),
            new OA\Response(response: 404, description: 'Médico no encontrado.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
    public function store(Request $request, string $doctorId)
    {
        $doctor = $this->findDoctor($doctorId);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
        ]);

        $error = $this->rangeError($validated['start_time'], $validated['end_time']);

        if ($error) {
            return response()->json([
                'message' => $error
            ], 422);
        }

        if ($this->overlaps($doctor->id, $validated['day_of_week'], $validated['start_time'], $validated['end_time'])) {
            return response()->json([
                'message' => 'La franja se superpone con otra ya cargada ese día'
            ], 422);
        }

        $schedule = DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        return response()->json([
            'message' => 'Franja horaria creada correctamente',
            'schedule' => $schedule->fresh()
        ], 201);
    }

    #[OA\Put(
        path: '/api/doctors/{id}/schedules/{scheduleId}',
        summary: 'Modificar una franja horaria de un médico',
        tags: ['Agenda de médicos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del médico', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 3),
            new OA\Parameter(name: 'scheduleId', description: 'ID de la franja', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1)
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'day_of_week', type: 'integer', minimum: 1, maximum: 7, example: 2),
                    new OA\Property(property: 'start_time', type: 'string', example: '10:00'),
                    new OA\Property(property: 'end_time', type: 'string', example: '14:00')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Franja actualizada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permiso para editar agendas.'),
            new OA\Response(response: 404, description: 'Médico o franja no encontrados.'),
            new OA\Response(response: 422, description: 'Datos inválidos.')
        ]
    )]
    public function update(Request $request, string $doctorId, string $scheduleId)
    {
        $doctor = $this->findDoctor($doctorId);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado'
            ], 404);
        }

        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->find($scheduleId);

        if (!$schedule) {
            return response()->json([
                'message' => 'Franja horaria no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'day_of_week' => ['sometimes', 'required', 'integer', 'between:1,7'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
        ]);

        $day = $validated['day_of_week'] ?? $schedule->day_of_week;
        $start = $validated['start_time'] ?? substr($schedule->start_time, 0, 5);
        $end = $validated['end_time'] ?? substr($schedule->end_time, 0, 5);

        $error = $this->rangeError($start, $end);

        if ($error) {
            return response()->json([
                'message' => $error
            ], 422);
        }

        if ($this->overlaps($doctor->id, $day, $start, $end, $schedule->id)) {
            return response()->json([
                'message' => 'La franja se superpone con otra ya cargada ese día'
            ], 422);
        }

        $schedule->update([
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        return response()->json([
            'message' => 'Franja horaria actualizada correctamente',
            'schedule' => $schedule->fresh()
        ], 200);
    }

    #[OA\Delete(
        path: '/api/doctors/{id}/schedules/{scheduleId}',
        summary: 'Eliminar una franja horaria de un médico',
        tags: ['Agenda de médicos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del médico', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 3),
            new OA\Parameter(name: 'scheduleId', description: 'ID de la franja', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1)
        ],
        responses: [
            new OA\Response(response: 200, description: 'Franja eliminada correctamente.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin permiso para editar agendas.'),
            new OA\Response(response: 404, description: 'Médico o franja no encontrados.')
        ]
    )]
    public function destroy(string $doctorId, string $scheduleId)
    {
        $doctor = $this->findDoctor($doctorId);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado'
            ], 404);
        }

        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->find($scheduleId);

        if (!$schedule) {
            return response()->json([
                'message' => 'Franja horaria no encontrada'
            ], 404);
        }

        $schedule->delete();

        return response()->json([
            'message' => 'Franja horaria eliminada correctamente'
        ], 200);
    }

    #[OA\Get(
        path: '/api/doctors/{id}/slots',
        summary: 'Obtener los turnos disponibles de un médico en una fecha',
        tags: ['Agenda de médicos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'id', description: 'ID del médico', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1), example: 3),
            new OA\Parameter(name: 'date', description: 'Fecha en formato YYYY-MM-DD', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: '2026-10-12')
        ],
        responses: [
            new OA\Response(response: 200, description: 'Slots de 15 minutos del día.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 404, description: 'Médico no encontrado.'),
            new OA\Response(response: 422, description: 'Fecha inválida o fuera de rango.')
        ]
    )]
    public function slots(Request $request, string $doctorId)
    {
        $doctor = $this->findDoctor($doctorId);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado'
            ], 404);
        }

        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $request->date)->startOfDay();

        if ($date->lt(today()) || $date->gt(today()->addYears(self::MAX_YEARS))) {
            return response()->json([
                'message' => 'La fecha debe estar entre hoy y los próximos ' . self::MAX_YEARS . ' años'
            ], 422);
        }

        $schedules = DoctorSchedule::where('doctor_id', $doctor->id)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->orderBy('start_time')
            ->get();

        $now = now();
        $slots = [];

        foreach ($schedules as $schedule) {
            $cursor = $date->copy()->setTimeFromTimeString($schedule->start_time);
            $end = $date->copy()->setTimeFromTimeString($schedule->end_time);

            while ($cursor->copy()->addMinutes(self::SLOT_MINUTES)->lte($end)) {
                if ($cursor->gt($now)) {
                    $slots[] = [
                        'start_time' => $cursor->format('H:i'),
                        'end_time' => $cursor->copy()->addMinutes(self::SLOT_MINUTES)->format('H:i'),
                    ];
                }

                $cursor->addMinutes(self::SLOT_MINUTES);
            }
        }

        return response()->json([
            'doctor_id' => $doctor->id,
            'date' => $date->format('Y-m-d'),
            'duration' => self::SLOT_MINUTES,
            'slots' => $slots
        ], 200);
    }

    private function findDoctor(string $id): ?Patient
    {
        return Patient::doctors()->active()->find($id);
    }

    private function rangeError(string $start, string $end): ?string
    {
        if ($start < self::OPEN_TIME || $end > self::CLOSE_TIME) {
            return 'La franja horaria debe estar dentro del rango ' . self::OPEN_TIME . ' a ' . self::CLOSE_TIME;
        }

        if ($end <= $start) {
            return 'La hora de fin debe ser posterior a la hora de inicio';
        }

        foreach ([$start, $end] as $time) {
            if ((int) substr($time, 3, 2) % self::SLOT_MINUTES !== 0) {
                return 'Los horarios deben ser múltiplos de ' . self::SLOT_MINUTES . ' minutos';
            }
        }

        return null;
    }

    private function overlaps(int $doctorId, int $day, string $start, string $end, ?int $ignoreId = null): bool
    {
        return DoctorSchedule::where('doctor_id', $doctorId)
            ->where('day_of_week', $day)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('start_time', '<', $end . ':00')
            ->where('end_time', '>', $start . ':00')
            ->exists();
    }
}