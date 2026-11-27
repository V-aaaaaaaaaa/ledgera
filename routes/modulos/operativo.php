<?php

use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\ConfiguracionInicialController;
use App\Http\Controllers\EgresoController;
use App\Http\Controllers\EntradaMercanciaController;
use App\Http\Controllers\ActividadNegocioController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProveedorController;
use Illuminate\Support\Facades\Route;

// =====================================================
// DASHBOARD OPERATIVO
// =====================================================

Route::get('/dashboard', [PanelController::class, 'operativo'])
    ->name('dashboard');

// =====================================================
// INCIDENCIAS
// =====================================================

Route::post('/incidencias', [IncidenciaController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('incidencias.store');

// =====================================================
// CONFIGURACIÓN INICIAL DEL PUESTO
// =====================================================

Route::middleware('rol:Dueno')
    ->prefix('configuracion-inicial')
    ->name('configuracion.')
    ->group(function () {

        Route::get('/', [ConfiguracionInicialController::class, 'mostrar'])
            ->name('inicial');

        Route::post('/', [ConfiguracionInicialController::class, 'guardar'])
            ->name('guardar');

        Route::post('/omitir', [ConfiguracionInicialController::class, 'omitir'])
            ->name('omitir');
    });

// =====================================================
// APERTURA DE CAJA — RF-020
// =====================================================

Route::prefix('caja')->name('caja.')->group(function () {

    Route::get('/estado', [CajaController::class, 'estado'])
        ->name('estado');

    Route::post('/abrir', [CajaController::class, 'abrir'])
        ->name('abrir');

    Route::put('/apertura', [CajaController::class, 'corregir'])
        ->name('corregir');
});

// =====================================================
// EGRESOS — RF-014
// =====================================================

Route::prefix('egresos')->name('egresos.')->group(function () {

    Route::get('/', [EgresoController::class, 'index'])
        ->name('index');

    Route::get('/datos', [EgresoController::class, 'datos'])
        ->name('datos');

    Route::post('/', [EgresoController::class, 'store'])
        ->name('store');

    Route::get('/proveedor/{proveedor}/facturas', [
        EgresoController::class, 'obligaciones'
    ])
        ->whereNumber('proveedor')
        ->middleware('poder:pagar_proveedores')   // Dueño o Vendedor encargado
        ->name('facturas');
});

// =====================================================
// CIERRE DE CAJA — RF-015
// =====================================================

Route::prefix('cierre-caja')->name('cierre.')->group(function () {

    Route::get('/', [CierreCajaController::class, 'index'])
        ->name('index');

    Route::get('/resumen', [CierreCajaController::class, 'resumen'])
        ->name('resumen');

    Route::post('/', [CierreCajaController::class, 'cerrar'])
        ->name('cerrar');

    Route::put('/umbral', [CierreCajaController::class, 'cambiarUmbral'])
        ->middleware('rol:Dueno')
        ->name('umbral');
});

// =====================================================
// PROVEEDORES
// =====================================================

// Consulta disponible para Dueño y Vendedor.

Route::get('/proveedores/datos', [ProveedorController::class, 'datos'])
    ->name('proveedores.datos');

// Crear y editar: Dueño o Vendedor encargado. Activar/desactivar: solo el Dueño.
Route::middleware('poder:gestionar_proveedores')
    ->prefix('proveedores')
    ->name('proveedores.')
    ->group(function () {

        Route::get('/', [ProveedorController::class, 'index'])
            ->name('index');

        Route::post('/', [ProveedorController::class, 'store'])
            ->name('store');

        Route::put('/{proveedor}', [ProveedorController::class, 'update'])
            ->name('update');

        Route::post('/{proveedor}/estado', [
            ProveedorController::class, 'cambiarEstado'
        ])
            ->middleware('rol:Dueno')
            ->name('estado');
    });

// =====================================================
// ACTIVIDAD DEL NEGOCIO (solo Dueño, solo lectura)
// =====================================================

Route::middleware('rol:Dueno')
    ->prefix('negocio/actividad')
    ->name('actividad.')
    ->group(function () {

        Route::get('/', [ActividadNegocioController::class, 'index'])
            ->name('index');

        Route::get('/datos', [ActividadNegocioController::class, 'datos'])
            ->name('datos');
    });

// =====================================================
// GESTIÓN DE USUARIOS DEL PUESTO
// =====================================================

Route::middleware('rol:Dueno')
    ->prefix('gestion')
    ->name('gestion.')
    ->group(function () {

        Route::get('/usuarios', [UsuarioController::class, 'index'])
            ->name('usuarios');

        Route::prefix('usuarios')
            ->name('usuarios.')
            ->group(function () {

                Route::get('/datos', [UsuarioController::class, 'datos'])
                    ->name('datos');

                Route::post('/', [UsuarioController::class, 'store'])
                    ->name('store');

                Route::put('/{usuario}', [UsuarioController::class, 'update'])
                    ->name('update');

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

// =====================================================
// CATÁLOGOS COMERCIALES
// =====================================================

Route::prefix('catalogos/{tipo}')
    ->where(['tipo' => 'clientes|productos'])
    ->name('catalogos.')
    ->group(function () {

        Route::get('/', [CatalogoController::class, 'index'])
            ->name('index');

        // Las entradas de mercancía gestionan el registro de productos.

        Route::post('/', [CatalogoController::class, 'store'])
            ->where('tipo', 'clientes')
            ->name('store');

        Route::put('/{id}', [CatalogoController::class, 'update'])
            ->whereNumber('id')
            ->name('update');

        Route::post('/{id}/estado', [CatalogoController::class, 'cambiarEstado'])
            ->whereNumber('id')
            ->name('estado');
    });

// =====================================================
// ENTRADAS DE MERCANCÍA — RF-006
// =====================================================

Route::prefix('entradas-mercancia')
    ->name('entradas.')
    ->group(function () {

        Route::get('/datos', [EntradaMercanciaController::class, 'datos'])
            ->name('datos');

        Route::post('/', [EntradaMercanciaController::class, 'store'])
            ->name('store');
    });