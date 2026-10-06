<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminRoleController;
use App\Http\Controllers\Api\HealthInsuranceController;
use App\Http\Controllers\Api\SalaController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\DoctorPatientController;

// Públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::apiResource('specialties', SpecialtyController::class)->only(['index', 'show']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

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
        Route::apiResource('health-insurances', HealthInsuranceController::class);
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
    });

    Route::get('/doctors/{id}/slots', [DoctorScheduleController::class, 'slots']);

    Route::middleware('permission:edit_schedules,view_all_appointments')->group(function () {
        Route::get('/doctors/{id}/schedules', [DoctorScheduleController::class, 'index']);
    });

    Route::middleware('permission:edit_schedules')->group(function () {
        Route::post('/doctors/{id}/schedules', [DoctorScheduleController::class, 'store']);
        Route::put('/doctors/{id}/schedules/{scheduleId}', [DoctorScheduleController::class, 'update']);
        Route::delete('/doctors/{id}/schedules/{scheduleId}', [DoctorScheduleController::class, 'destroy']);
    });
});
