<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\ConfiguracionInicialController;
use App\Http\Controllers\EgresoController;
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

// Configuración inicial del puesto (primer ingreso del Dueño): productos y calidades
Route::middleware('rol:Dueno')
    ->prefix('configuracion-inicial')
    ->group(function () {
        Route::get('/', [ConfiguracionInicialController::class, 'mostrar'])->name('configuracion.inicial');
        Route::post('/', [ConfiguracionInicialController::class, 'guardar'])->name('configuracion.guardar');
        Route::post('/omitir', [ConfiguracionInicialController::class, 'omitir'])->name('configuracion.omitir');
    });

// Egresos (RF-014): Dueño y Vendedor. Los pagos a proveedores: Dueño o Vendedor encargado
// (lo valida el controlador y la ruta de facturas).
Route::prefix('egresos')->name('egresos.')->group(function () {
    Route::get('/', [EgresoController::class, 'index'])->name('index');
    Route::get('/datos', [EgresoController::class, 'datos'])->name('datos');
    Route::post('/', [EgresoController::class, 'store'])->name('store');
    Route::get('/proveedor/{proveedor}/facturas', [EgresoController::class, 'obligaciones'])
        ->whereNumber('proveedor')->middleware('poder:pagar_proveedores')->name('facturas');
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

// Proveedores: crear y editar -> Dueño o Vendedor encargado.
// Activar/desactivar -> solo el Dueño.
Route::middleware('poder:gestionar_proveedores')
    ->prefix('proveedores')
    ->name('proveedores.')
    ->group(function () {
        Route::get('/', [ProveedorController::class, 'index'])->name('index');
        Route::post('/', [ProveedorController::class, 'store'])->name('store');
        Route::put('/{proveedor}', [ProveedorController::class, 'update'])->name('update');
        Route::post('/{proveedor}/estado', [ProveedorController::class, 'cambiarEstado'])
            ->middleware('rol:Dueno')->name('estado');
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

                // Nombrar o quitar Vendedor encargado (solo el Dueño)
                Route::post('/{usuario}/encargado', [
                    UsuarioController::class, 'cambiarEncargado'
                ])->name('encargado');
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
