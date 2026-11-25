<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProveedorController;
use Illuminate\Support\Facades\Route;

// Dashboard operativo
Route::get('/dashboard', [
    PanelController::class, 'operativo'
])->name('dashboard');

// Incidencias
Route::post('/incidencias', [
    IncidenciaController::class, 'store'
])->middleware('throttle:10,1')->name('incidencias.store');

// Proveedores: el Vendedor solo consulta la lista (la usa en facturas de compra)
Route::get('/proveedores/datos', [
    ProveedorController::class, 'datos'
])->name('proveedores.datos');

// Proveedores: crear, editar y activar/desactivar es solo del Dueño
Route::middleware('rol:Dueno')
    ->prefix('proveedores')
    ->name('proveedores.')
    ->group(function () {
        Route::get('/', [ProveedorController::class, 'index'])->name('index');
        Route::post('/', [ProveedorController::class, 'store'])->name('store');
        Route::put('/{proveedor}', [ProveedorController::class, 'update'])->name('update');
        Route::post('/{proveedor}/estado', [ProveedorController::class, 'cambiarEstado'])->name('estado');
    });

// Gestión exclusiva del dueño
Route::middleware('rol:Dueno')
    ->prefix('gestion')
    ->name('gestion.')
    ->group(function () {

        Route::get('/usuarios', [
            UsuarioController::class, 'index'
        ])->name('usuarios');

        Route::prefix('usuarios')
            ->name('usuarios.')
            ->group(function () {

                Route::get('/datos', [
                    UsuarioController::class, 'datos'
                ])->name('datos');

                Route::post('/', [
                    UsuarioController::class, 'store'
                ])->name('store');

                Route::put('/{usuario}', [
                    UsuarioController::class, 'update'
                ])->name('update');

                Route::post('/{usuario}/estado', [
                    UsuarioController::class, 'cambiarEstado'
                ])->name('estado');

                Route::post('/{usuario}/restablecer-clave', [
                    UsuarioController::class, 'restablecerClave'
                ])->name('restablecer');
            });
    });
