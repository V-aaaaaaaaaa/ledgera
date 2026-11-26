<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProveedorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatalogoController;

// Dashboard operativo
Route::get('/dashboard', [
    PanelController::class, 'operativo'
])->name('dashboard');

// Incidencias
Route::post('/incidencias', [
    IncidenciaController::class, 'store'
])->middleware('throttle:10,1')->name('incidencias.store');

// Caja (RF-020): Dueño y Vendedor
Route::prefix('caja')->name('caja.')->group(function () {
    Route::get('/estado', [CajaController::class, 'estado'])->name('estado');
    Route::post('/abrir', [CajaController::class, 'abrir'])->name('abrir');
    Route::put('/apertura', [CajaController::class, 'corregir'])->name('corregir');
});

// Cierre de caja (RF-015): Dueño y Vendedor. El límite de descuadre solo lo cambia el Dueño.
Route::prefix('cierre-caja')->name('cierre.')->group(function () {
    Route::get('/', [CierreCajaController::class, 'index'])->name('index');
    Route::get('/resumen', [CierreCajaController::class, 'resumen'])->name('resumen');
    Route::post('/', [CierreCajaController::class, 'cerrar'])->name('cerrar');
    Route::put('/umbral', [CierreCajaController::class, 'cambiarUmbral'])
        ->middleware('rol:Dueno')->name('umbral');
});

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
// Catálogos comerciales: Clientes y Productos
Route::prefix('catalogos/{tipo}')
    ->where(['tipo' => 'clientes|productos'])
    ->name('catalogos.')
    ->group(function () {

        Route::get('/', [CatalogoController::class, 'index'])
            ->name('index');

        Route::post('/', [CatalogoController::class, 'store'])
            ->name('store');

        Route::put('/{id}', [CatalogoController::class, 'update'])
            ->whereNumber('id')
            ->name('update');

        Route::post('/{id}/estado', [CatalogoController::class, 'cambiarEstado'])
            ->whereNumber('id')
            ->name('estado');
    });
