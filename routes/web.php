<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// ---------- Público ----------
Route::get('/', [AuthController::class, 'mostrarLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.enviar');

// ---------- Con sesión ----------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Cambio de clave: permitido incluso con clave temporal
    Route::middleware('cuenta:temporal')->group(function () {
        Route::get('/cambiar-clave', [AuthController::class, 'mostrarCambioClave'])->name('clave.form');
        Route::post('/cambiar-clave', [AuthController::class, 'cambiarClave'])->name('clave.guardar');
    });

    // Todo lo demás exige cuenta vigente y clave definitiva.
    // Cada área tiene su propio archivo para que dos personas no se pisen en git.
    Route::middleware('cuenta')->group(function () {
        Route::middleware('rol:Administrador')->prefix('admin')->name('admin.')
            ->group(base_path('routes/modulos/admin.php'));

        Route::middleware('rol:Dueno,Vendedor')
            ->group(base_path('routes/modulos/operativo.php'));
    });
});
