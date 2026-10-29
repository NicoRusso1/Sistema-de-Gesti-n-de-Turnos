<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorSpecialtyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminRoleController;
use App\Http\Controllers\Api\HealthInsuranceController;
use App\Http\Controllers\Api\SalaController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\DoctorPatientController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientHealthInsuranceController;

// Públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::apiResource('specialties', SpecialtyController::class)->only(['index', 'show']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/mercadopago/webhook', [PaymentController::class, 'webhook']);

// Requieren token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Requieren token y ser SuperAdmin o Administrador Propietario
    Route::middleware('superadmin_or_owner')->group(function () {
        Route::post('/users/{id}/admin-role', [AdminRoleController::class, 'grant']);
        Route::delete('/users/{id}/admin-role', [AdminRoleController::class, 'revoke']);
    });
    Route::middleware('permission:view_assigned_patients')->group(function () {
        Route::get('/medicos/me/pacientes', [DoctorPatientController::class, 'index']);
    });
    // Requieren token y rol Administrator (el SuperAdmin entra por herencia)
    Route::middleware('role:Administrator')->group(function () {
        Route::apiResource('specialties', SpecialtyController::class)->except(['index', 'show']);
        Route::post('/users', [UserController::class, 'store']);

        Route::get('/doctors', [DoctorController::class, 'index']);
        Route::delete('/doctors/{id}', [DoctorController::class, 'destroy']);
        Route::post('/doctors/{id}/restore', [DoctorController::class, 'restore']);
        // ESPECIALIDADES DE UN MÉDICO
        Route::get('/doctors/{id}/specialties', [DoctorSpecialtyController::class, 'index']);
        Route::post('/doctors/{id}/specialties', [DoctorSpecialtyController::class, 'store']);
        Route::put('/doctors/{id}/specialties', [DoctorSpecialtyController::class, 'sync']);
        Route::delete('/doctors/{id}/specialties/{specialtyId}', [DoctorSpecialtyController::class, 'destroy']);
        Route::apiResource('health-insurances', HealthInsuranceController::class)->except(['index', 'show']);
        Route::post('/health-insurances/{id}/restore', [HealthInsuranceController::class, 'restore']);

        // GESTIÓN DE SALAS Y DISPONIBILIDAD
        Route::get('/salas/disponibilidad', [SalaController::class, 'disponibilidad']);
        Route::patch('/salas/{id}/estado', [SalaController::class, 'cambiarEstado']);
        Route::apiResource('salas', SalaController::class);

        // RUTAS DE GESTIÓN DE USUARIOS
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/restore', [UserController::class, 'restore']);
        Route::get('/users/{id}/permissions', [UserController::class, 'permissions']);
        Route::put('/users/{id}/permissions', [UserController::class, 'updatePermissions']);
        
        // CERTIFICADO DIGITAL
        Route::post('/appointments/{id}/certificate', [AppointmentController::class, 'issueCertificate']);
        Route::get('/appointments/{id}/certificate/download', [AppointmentController::class, 'downloadCertificate']);
    });

    Route::get('/pagos', [PaymentController::class, 'index']);
    Route::get('/me/notifications', [NotificationController::class, 'myNotifications']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/send-reminders', [NotificationController::class, 'sendReminders']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    Route::get('/me/next-appointment', [AppointmentController::class, 'nextAppointment']);
    
    Route::apiResource('health-insurances', HealthInsuranceController::class)->only(['index', 'show']);

    Route::get('/me/health-insurance', [PatientHealthInsuranceController::class, 'show']);
    Route::put('/me/health-insurance', [PatientHealthInsuranceController::class, 'update']);
    Route::delete('/me/health-insurance', [PatientHealthInsuranceController::class, 'destroy']);

    Route::get('/doctors/{id}/slots', [DoctorScheduleController::class, 'slots']);
    // Filtro del flujo de reserva: médicos asignados a una especialidad
    Route::get('/specialties/{id}/doctors', [DoctorSpecialtyController::class, 'doctorsBySpecialty']);

    // TURNOS: permission: valida el flag (el admin pasa siempre) y después el
    // controlador / FormRequest filtra por turno: el médico solo los que tiene
    // asignados y el paciente solo los propios (Appointment::isVisibleTo)
    Route::post('/appointments', [AppointmentController::class, 'store'])
        ->middleware('permission:view_own_appointments');
    Route::get('/appointments', [AppointmentController::class, 'index'])
        ->middleware(['permission:view_own_appointments,view_assigned_patients,view_all_appointments', 'appointment_scope']);
    Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])
        ->middleware('permission:cancel_appointments,view_own_appointments');
    Route::patch('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])
        ->middleware('permission:edit_schedules,view_own_appointments');
    // GT-43: asignación manual de sala (Secretaria / Administrador, el tipo se valida en el controlador)
    Route::get('/appointments/{id}/available-salas', [AppointmentController::class, 'availableSalas'])
        ->middleware('permission:view_all_appointments');
    Route::patch('/appointments/{id}/sala', [AppointmentController::class, 'assignSala'])
        ->middleware('permission:view_all_appointments');

    // Pagos: solo el paciente titular del turno (se valida en el controlador)
    Route::middleware('permission:view_own_appointments')->group(function () {
        Route::post('/appointments/{id}/payments', [PaymentController::class, 'store']);
        Route::post('/payments/{id}/verify', [PaymentController::class, 'verify']);
    });

    Route::middleware('permission:edit_schedules,view_all_appointments')->group(function () {
        Route::get('/doctors/{id}/schedules', [DoctorScheduleController::class, 'index']);
    });

    Route::middleware('permission:edit_schedules')->group(function () {
        Route::post('/doctors/{id}/schedules', [DoctorScheduleController::class, 'store']);
        Route::put('/doctors/{id}/schedules/{scheduleId}', [DoctorScheduleController::class, 'update']);
        Route::delete('/doctors/{id}/schedules/{scheduleId}', [DoctorScheduleController::class, 'destroy']);
    });
});
