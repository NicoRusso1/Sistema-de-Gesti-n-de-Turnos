<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
class DoctorPatientController extends Controller
{
    #[OA\Get(
        path: '/api/medicos/me/pacientes',
        summary: 'Listar los pacientes con turno asignado al médico autenticado',
        tags: ['Médicos'],
        security: [
            ['sanctum' => []]
        ],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Listado de pacientes asignados.'),
            new OA\Response(response: 401, description: 'No autenticado.'),
            new OA\Response(response: 403, description: 'Sin el permiso view_assigned_patients o no es médico.')
        ]
    )]

    public function index(Request $request)
    {
        $doctor = $request->user();

        if (!$doctor->isDoctor()) {
            return response()->json([
                'message' => 'Solo los médicos pueden consultar sus pacientes asignados'
            ], 403);
        }
        $query = Patient::assignedTo($doctor)
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
            ])
            ->with([
                'personalData:id,patient_id,national_id',
                'healthInsurance',
                'healthInsuranceRecord:id,patient_id,member_number',
            ])
            ->withCount([
                'appointmentsAsPatient as appointments_count' =>
                    fn($q) => $q->where('doctor_id', $doctor->id),
            ]);
        // El closure es obligatorio: sin él, el orWhere escaparía del filtro por médico
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $patients = $query->orderBy('last_name')->orderBy('first_name')->get();

        $patients->each(function ($patient) {
            $patient->health_insurance_number = $patient->healthInsuranceRecord?->member_number;
            $patient->unsetRelation('healthInsuranceRecord');
        });

        return response()->json($patients, 200);
    }
}