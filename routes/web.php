
<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::redirect('/', '/login');

Route::get('/login', [
    AuthController::class,
    'mostrarLogin'
])->name('login');

Route::post('/login', [
    AuthController::class,
    'login'
])->middleware('throttle:15,1')->name('login.enviar');

// Rutas autenticadas
Route::middleware('auth')->group(function () {

    Route::post('/logout', [
        AuthController::class,
        'logout'
    ])->name('logout');

    // Cambio de contraseña
    Route::middleware('cuenta:temporal')->group(function () {

        Route::get('/cambiar-clave', [
            AuthController::class,
            'mostrarCambioClave'
        ])->name('clave.form');

        Route::post('/cambiar-clave', [
            AuthController::class,
            'cambiarClave'
        ])->name('clave.guardar');
    });

    // Módulos protegidos
    Route::middleware('cuenta')->group(function () {

        // Administrador del sistema
        Route::middleware('rol:Administrador')
            ->prefix('admin')
            ->name('admin.')
            ->group(base_path('routes/modulos/admin.php'));

        // Dueño y Vendedor
        Route::middleware('rol:Dueno,Vendedor')
            ->group(base_path('routes/modulos/operativo.php'));
    });
});
