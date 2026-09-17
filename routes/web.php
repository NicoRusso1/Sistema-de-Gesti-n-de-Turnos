<?php

use App\Http\Controllers\Admin\MedicoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\User;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Nueva ruta directa para actualizar rol sin controlador extra
    Route::patch('/users/{user}/role', function (Request $request, User $user) {
        $request->validate([
            'role_id' => ['required', 'integer'],
        ]);

        $user->update([
            'role_id' => $request->role_id,
        ]);

        return back()->with('status', 'role-updated');
    })->name('users.role.update');
});

Route::middleware(['auth', 'role:Administrator'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/medicos/crear', [MedicoController::class, 'create'])->name('medicos.create');
    Route::post('/medicos', [MedicoController::class, 'store'])->name('medicos.store');
});

require __DIR__.'/auth.php';