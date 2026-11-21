<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\IncidenciaController as AdminIncidenciaController;
use App\Http\Controllers\Admin\PuestoController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

// Rutas del ADMINISTRADOR. Ya llevan el prefijo /admin y el nombre admin.*
// Aquí se agregan: Usuarios, Puestos, Auditoría y Reportes del sistema.

Route::get('/', [PanelController::class, 'admin'])->name('dashboard');

// ---------- Usuarios ----------
Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios');
Route::prefix('usuarios')->name('usuarios.')->group(function () {
    Route::get('/datos', [UsuarioController::class, 'datos'])->name('datos');
    Route::post('/', [UsuarioController::class, 'store'])->name('store');
    Route::put('/{usuario}', [UsuarioController::class, 'update'])->name('update');
    Route::post('/{usuario}/restablecer-clave', [UsuarioController::class, 'restablecerClave'])->name('restablecer');
    Route::post('/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('estado');
});

// ---------- Puestos ----------
Route::get('/puestos', [PuestoController::class, 'index'])->name('puestos');
Route::prefix('puestos')->name('puestos.')->group(function () {
    Route::get('/datos', [PuestoController::class, 'datos'])->name('datos');
    Route::post('/', [PuestoController::class, 'store'])->name('store');
    Route::put('/{puesto}', [PuestoController::class, 'update'])->name('update');
    Route::post('/{puesto}/dueno', [PuestoController::class, 'cambiarDueno'])->name('dueno');
    Route::post('/{puesto}/estado', [PuestoController::class, 'cambiarEstado'])->name('estado');
});

// ---------- Auditoría ----------
Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria');
Route::get('/auditoria/datos', [AuditoriaController::class, 'datos'])->name('auditoria.datos');

// ---------- Reportes e indicadores del sistema ----------
Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes');
Route::get('/reportes/datos', [ReporteController::class, 'datos'])->name('reportes.datos');

// ---------- Incidencias (soporte) ----------
Route::get('/incidencias/datos', [AdminIncidenciaController::class, 'datos'])->name('incidencias.datos');
Route::post('/incidencias/{incidencia}', [AdminIncidenciaController::class, 'actualizar'])->name('incidencias.actualizar');
