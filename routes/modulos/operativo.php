<?php

use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

// Rutas del DUEÑO y del VENDEDOR (ambos entran aquí).
// Aquí se agregan: Ventas, Inventario, Fiado, Egresos, Cierre de caja...
// Lo exclusivo del Dueño se marca en la ruta con ->middleware('rol:Dueno').

Route::get('/dashboard', [PanelController::class, 'operativo'])->name('dashboard');

// Reportar un problema al soporte (máximo 10 reportes por minuto por persona)
Route::post('/incidencias', [IncidenciaController::class, 'store'])->middleware('throttle:10,1')->name('incidencias.store');
