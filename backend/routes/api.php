<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpecialtyController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\UserController;

// Públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::apiResource('specialties', SpecialtyController::class)->only(['index', 'show']);

// Requieren token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Requieren token y rol Administrator (el SuperAdmin entra por herencia)
    Route::middleware('role:Administrator')->group(function () {
        Route::apiResource('specialties', SpecialtyController::class)->except(['index', 'show']);
        Route::post('/specialties/{id}/restore', [SpecialtyController::class, 'restore']);

        Route::get('/doctors', [DoctorController::class, 'index']);
        Route::delete('/doctors/{id}', [DoctorController::class, 'destroy']);
        Route::post('/doctors/{id}/restore', [DoctorController::class, 'restore']);
        
        // RUTAS DE GESTIÓN DE USUARIOS
        Route::get('/users', [UserController::class, 'index']);
    });
});
