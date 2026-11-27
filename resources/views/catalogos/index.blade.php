@extends('layouts.operativo')

@section('titulo', 'LEDGERA - ' . $cfg['titulo'])
@section('tema', 'tema-inventario')

@push('estilos')

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="{{ asset('css/catalogos.css') }}?v={{ filemtime(public_path('css/catalogos.css')) }}">

@endpush

@section('operativo-contenido')

@php
    $esInventario = $tipo === 'productos';

    $buscar = request()->query('buscar', '');
    $buscar = is_string($buscar) ? $buscar : '';

    $estado = request()->query('estado');

    $moneda = static fn ($valor) =>
        '$' . number_format((float) $valor, 2, ',', '.');

    $pagina = !$esInventario || $pestana === 'catalogo'
        ? $registros
        : ($pestana === 'inventario' ? $existencias : $entradas);
@endphp

<header class="cat-header">

    <a href="{{ route('dashboard') }}"
       class="cat-back"
       aria-label="Volver al dashboard">

        <i class="fas fa-arrow-left" aria-hidden="true"></i>
        Volver
    </a>

    <strong>Led<span class="logo-acento">gera</span></strong>

    <div class="cat-user">

        <span>{{ auth()->user()->nombre_completo }}</span>

        <i class="fas fa-user-circle" aria-hidden="true"></i>
    </div>

</header>

<main class="cat-main">

    {{-- ENCABEZADO DEL MÓDULO --}}

    <div class="cat-heading">

        <div>

            <small>Gestión comercial</small>

            <h1>{{ $cfg['titulo'] }}</h1>

            <p>
                {{ $esInventario
                    ? 'Consulta existencias, compras y catálogo de tu puesto.'
                    : 'Consulta y administra los clientes de tu puesto.' }}
            </p>

        </div>

        <button type="button"
                class="cat-primary"
                onclick="document.getElementById('crear').showModal()">

            {{ $esInventario ? '+ Nueva entrada' : '+ Registrar cliente' }}
        </button>

    </div>

    {{-- NAVEGACIÓN --}}

    @if($esInventario)

        <nav class="cat-nav" aria-label="Inventario y compras">

            @foreach([
                'inventario' => 'Inventario',
                'entradas' => 'Entradas',
                'catalogo' => 'Catálogo'
            ] as $clave => $etiqueta)

                <a href="{{ route('catalogos.index', [
                        'tipo' => $tipo,
                        'pestana' => $clave
                    ]) }}"

                   @class(['selected' => $pestana === $clave])

                   @if($pestana === $clave)
                       aria-current="page"
                   @endif>

                    {{ $etiqueta }}
                </a>

            @endforeach

        </nav>

    @endif

    {{-- INDICADORES --}}

    <div class="cat-stats">

        <article>

            <span>
                {{ $esInventario
                    ? 'Productos registrados'
                    : 'Clientes registrados' }}
            </span>

            <strong>{{ $total }}</strong>

        </article>

        <article>

            <span>
                {{ $esInventario
                    ? 'Productos activos'
                    : 'Clientes activos' }}
            </span>

            <strong>{{ $activos }}</strong>

        </article>

        <article>

            @if($esInventario)

                <span>Productos inactivos</span>

                <strong>{{ $total - $activos }}</strong>

            @else

                <span>Saldo pendiente</span>

                <strong>{{ $moneda($saldoTotal) }}</strong>

            @endif

        </article>

    </div>

    {{-- MENSAJES --}}

    @if(session('success'))

        <div class="cat-message success" role="status">

            {{ session('success') }}

        </div>

    @endif

    @if($errors->any())

        <div class="cat-message error" role="alert">

            @foreach($errors->all() as $error)

                <div>{{ $error }}</div>

            @endforeach

        </div>

    @endif

    {{-- HISTORIAL DE UNA PRESENTACIÓN --}}

    @if($esInventario && $pestana === 'entradas' && $presentacionSeleccionada)

        <div class="cat-message success">

            <strong>Historial de compras:</strong>

            {{ $presentacionSeleccionada->nombre_producto }} ·
            {{ $presentacionSeleccionada->nombre_tipo }} ·
            {{ $presentacionSeleccionada->unidad_comercial }}

            <a class="cat-secondary"
               href="{{ route('catalogos.index', [
                   'tipo' => 'productos',
                   'pestana' => 'entradas'
               ]) }}">

                Ver todas las entradas

            </a>

            <p>
                Se muestran las facturas que contienen esta
                presentación, con su detalle completo.
            </p>

        </div>

    @endif

    {{-- FILTROS --}}

    <form method="GET"
          action="{{ route('catalogos.index', $tipo) }}"
          class="cat-filters">

        @if($esInventario)

            <input type="hidden"
                   name="pestana"
                   value="{{ $pestana }}">

            @if($pestana === 'entradas' && $presentacionSeleccionada)

                <input type="hidden"
                       name="inventario"
                       value="{{ $presentacionSeleccionada->id_inventario }}">

            @endif

        @endif

        <input type="search"
               name="buscar"
               value="{{ $buscar }}"
               maxlength="150"
               aria-label="Buscar registros"

               placeholder="{{ !$esInventario
                    ? 'Nombre o teléfono'
                    : ($pestana === 'entradas'
                        ? 'Proveedor o factura'
                        : ($pestana === 'inventario'
                            ? 'Producto, tipo o unidad'
                            : 'Producto o tipo')) }}">

        @if(!$esInventario || $pestana !== 'entradas')

            <select name="estado" aria-label="Estado">

                <option value="">Todos los estados</option>

                <option value="Activo"
                    @selected($estado === 'Activo')>

                    Activos

                </option>

                <option value="Inactivo"
                    @selected($estado === 'Inactivo')>

                    Inactivos

                </option>

            </select>

        @endif

        <button type="submit" class="cat-primary">

            Filtrar

        </button>

        @php

            $limpiar = ['tipo' => $tipo];

            if ($esInventario) {

                $limpiar['pestana'] = $pestana;

                if ($pestana === 'entradas' && $presentacionSeleccionada) {

                    $limpiar['inventario'] =
                        $presentacionSeleccionada->id_inventario;
                }
            }

        @endphp

        <a class="cat-secondary"
           href="{{ route('catalogos.index', $limpiar) }}">

            Limpiar

        </a>

    </form>

    {{-- =================================================
         PESTAÑA INVENTARIO
         ================================================= --}}

    @if($esInventario && $pestana === 'inventario')

        <div class="cat-table-wrapper">

            <table class="cat-table">

                <thead>

                    <tr>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Unidad comercial</th>
                        <th>Existencias</th>
                        <th>Precio de venta</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse($existencias as $r)

                        <tr>

                            <td>{{ $r->nombre_producto }}</td>

                            <td>{{ $r->nombre_tipo }}</td>

                            <td>

                                {{ $r->unidad_comercial }}

                                @if($r->unidad_comercial === 'Por definir')

                                    <div>
                                        <small>
                                            Unidad pendiente de identificación
                                        </small>
                                    </div>

                                @endif

                            </td>

                            <td>

                                <strong>
                                    {{ number_format(
                                        $r->stock_actual,
                                        0,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>

                                @if($r->stock_actual <= $r->stock_minimo)

                                    <div><small>Stock bajo</small></div>

                                @endif

                            </td>

                            <td class="cat-money">

                                {{ $r->precio_venta_diario > 0
                                    ? $moneda($r->precio_venta_diario)
                                    : 'Sin precio' }}

                            </td>

                            <td>

                                <span @class([
                                    'cat-badge',
                                    'active' => $r->estado === 'Activo',
                                    'inactive' => $r->estado !== 'Activo'
                                ])>

                                    {{ $r->estado }}

                                </span>

                            </td>

                            <td>

                                {{-- Ajuste de inventario (RF-008): merma o conteo --}}
                                <button type="button"
                                        class="cat-primary cat-btn-ajustar"
                                        data-ajustar
                                        data-id="{{ $r->id_inventario }}"
                                        data-nombre="{{ $r->nombre_producto }} {{ $r->nombre_tipo }} ({{ $r->unidad_comercial }})"
                                        data-stock="{{ (int) $r->stock_actual }}">
                                    <i class="fas fa-scale-balanced"></i> Ajustar
                                </button>

                                <a class="cat-secondary"
                                   href="{{ route('catalogos.index', [
                                       'tipo' => 'productos',
                                       'pestana' => 'entradas',
                                       'inventario' => $r->id_inventario
                                   ]) }}">

                                    Historial

                                </a>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="7" class="cat-empty">

                                No hay presentaciones con estos filtros.
                                Los tipos sin mercancía se encuentran
                                en Catálogo.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    {{-- =================================================
         PESTAÑA ENTRADAS
         ================================================= --}}

    @elseif($esInventario && $pestana === 'entradas')

        <div class="cat-table-wrapper">

            <table class="cat-table">

                <thead>

                    <tr>
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Factura / Referencia</th>
                        <th>Total calculado</th>
                        <th>Valor acordado</th>
                        <th>Pagado</th>
                        <th>Saldo pendiente</th>
                        <th>Estado de pago</th>
                    </tr>

                </thead>

                <tbody>

                    @forelse($entradas as $r)

                        <tr>

                            <td>

                                <time datetime="{{ str_replace(' ', 'T', $r->fecha_entrada) }}">

                                    {{ \Carbon\Carbon::parse(
                                        $r->fecha_entrada
                                    )->format('d/m/Y H:i') }}

                                </time>

                            </td>

                            <td>{{ $r->nombre_razon_social }}</td>

                            <td>{{ $r->documento_soporte }}</td>

                            <td class="cat-money">
                                {{ $moneda($r->total_calculado) }}
                            </td>

                            <td class="cat-money">
                                {{ $moneda($r->valor_acordado) }}
                            </td>

                            <td class="cat-money">
                                {{ $moneda($r->pagado) }}
                            </td>

                            <td class="cat-money">
                                {{ $moneda($r->saldo_pendiente) }}
                            </td>

                            <td>

                                <span @class([
                                    'cat-badge',
                                    'active' => $r->estado_pago === 'Pagado',
                                    'inactive' => $r->estado_pago !== 'Pagado'
                                ])>

                                    {{ $r->estado_pago }}

                                </span>

                            </td>

                        </tr>

                        <tr>

                            <td colspan="8">

                                <details>

                                    <summary>

                                        Ver detalle de factura
                                        ({{ $r->detalles->count() }} líneas)

                                    </summary>

                                    <div class="cat-table-wrapper">

                                        <table class="cat-table"
                                               aria-label="Detalle de factura {{ $r->documento_soporte }}">

                                            <thead>

                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Tipo</th>
                                                    <th>Unidad</th>
                                                    <th>Cantidad recibida</th>
                                                    <th>Costo unitario</th>
                                                    <th>Subtotal</th>
                                                </tr>

                                            </thead>

                                            <tbody>

                                                @forelse($r->detalles as $d)

                                                    <tr>

                                                        <td>{{ $d->nombre_producto }}</td>

                                                        <td>{{ $d->nombre_tipo }}</td>

                                                        <td>{{ $d->unidad_comercial }}</td>

                                                        <td>
                                                            {{ number_format(
                                                                $d->cantidad_recibida,
                                                                0,
                                                                ',',
                                                                '.'
                                                            ) }}
                                                        </td>

                                                        <td class="cat-money">
                                                            {{ $moneda($d->costo_unitario) }}
                                                        </td>

                                                        <td class="cat-money">
                                                            {{ $moneda($d->subtotal) }}
                                                        </td>

                                                    </tr>

                                                @empty

                                                    <tr>

                                                        <td colspan="6" class="cat-empty">

                                                            No hay detalle disponible
                                                            para esta factura.

                                                        </td>

                                                    </tr>

                                                @endforelse

                                            </tbody>

                                        </table>

                                    </div>

                                </details>

                            </td>

                        </tr>

                    @empty

                        <tr>

                            <td colspan="8" class="cat-empty">

                                No hay entradas con estos filtros.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    {{-- =================================================
         CATÁLOGO DE PRODUCTOS Y CLIENTES
         ================================================= --}}

    @else

        <div class="cat-table-wrapper">

            <table class="cat-table">

                <thead>

                    <tr>

                        <th>Nombre</th>

                        @if($esInventario)

                            <th>Tipos</th>

                        @else

                            <th>Teléfono</th>
                            <th>Plazo de crédito</th>

                        @endif

                        <th>Estado</th>

                        @unless($esInventario)

                            <th>Saldo pendiente</th>

                        @endunless

                        @if($esDueno)

                            <th>Acciones</th>

                        @endif

                    </tr>

                </thead>

                <tbody>

                    @forelse($registros as $r)

                        <tr>

                            <td>

                                <strong>{{ $r->{$cfg['nombre']} }}</strong>

                            </td>

                            @if($esInventario)

                                <td>

                                    {{ $r->tipos
                                        ->pluck('nombre_tipo')
                                        ->implode(' · ')
                                        ?: 'Sin tipos registrados' }}

                                </td>

                            @else

                                <td>{{ $r->telefono }}</td>

                                <td>{{ $r->plazo_credito_dias }} días</td>

                            @endif

                            <td>

                                <span @class([
                                    'cat-badge',
                                    'active' => $r->estado === 'Activo',
                                    'inactive' => $r->estado !== 'Activo'
                                ])>

                                    {{ $r->estado }}

                                </span>

                            </td>

                            @unless($esInventario)

                                <td class="cat-money">

                                    {{ $moneda($r->saldo) }}

                                </td>

                            @endunless

                            @if($esDueno)

                                <td>

                                    <div class="cat-actions">

                                        <button type="button"
                                                class="cat-secondary"
                                                onclick="document.getElementById('editar-{{ $r->{$cfg['id']} }}').showModal()">

                                            Editar

                                        </button>

                                        <form method="POST"
                                              action="{{ route('catalogos.estado', [
                                                  $tipo,
                                                  $r->{$cfg['id']}
                                              ]) }}"
                                              onsubmit="return confirm('¿Confirmar cambio de estado?')">

                                            @csrf

                                            <input type="hidden"
                                                   name="estado"
                                                   value="{{ $r->estado === 'Activo'
                                                       ? 'Inactivo'
                                                       : 'Activo' }}">

                                            <button type="submit"
                                                    class="cat-secondary">

                                                {{ $r->estado === 'Activo'
                                                    ? 'Desactivar'
                                                    : 'Activar' }}

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            @endif

                        </tr>

                    @empty

                        <tr>

                            <td colspan="{{ ($esInventario ? 3 : 5) + ($esDueno ? 1 : 0) }}"
                                class="cat-empty">

                                No hay registros con estos filtros.

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    @endif

    {{-- PAGINACIÓN --}}

    <nav class="cat-pagination" aria-label="Paginación">

        @if($pagina->previousPageUrl())

            <a class="cat-secondary"
               href="{{ $pagina->previousPageUrl() }}">

                Anterior

            </a>

        @endif

        Página {{ $pagina->currentPage() }}
        de {{ $pagina->lastPage() }}
        · {{ $pagina->total() }} registros

        @if($pagina->nextPageUrl())

            <a class="cat-secondary"
               href="{{ $pagina->nextPageUrl() }}">

                Siguiente

            </a>

        @endif

    </nav>

</main>

{{-- =====================================================
     MODAL DE ENTRADAS O REGISTRO DE CLIENTES
     ===================================================== --}}

@if($esInventario)

    @include('catalogos.registro-producto')
    @include('catalogos.ajuste-inventario')

@else

    <dialog id="crear"
            class="cat-modal"
            aria-labelledby="crear-cliente-titulo">

        <form method="POST"
              action="{{ route('catalogos.store', 'clientes') }}">

            @csrf

            <h2 id="crear-cliente-titulo">Registrar cliente</h2>

            <label for="crear-nombre">Nombre *</label>

            <input id="crear-nombre"
                   name="nombre"
                   value="{{ old('nombre') }}"
                   maxlength="150"
                   required>

            <label for="crear-telefono">Teléfono *</label>

            <input id="crear-telefono"
                   name="telefono"
                   type="tel"
                   value="{{ old('telefono') }}"
                   maxlength="20"
                   required>

            <label for="crear-plazo">Plazo de crédito en días</label>

            <input id="crear-plazo"
                   type="number"
                   name="plazo"
                   min="1"
                   max="3650"
                   value="{{ old('plazo', 15) }}"
                   @disabled(!$esDueno)
                   required>

            <label class="cat-consent">

                <input type="checkbox"
                       name="consentimiento"
                       value="1"
                       required>

                Confirmo que cuento con autorización para registrar
                y tratar estos datos de contacto.

            </label>

            <div class="cat-modal-actions">

                <button type="button"
                        class="cat-secondary"
                        onclick="this.closest('dialog').close()">

                    Cancelar

                </button>

                <button type="submit" class="cat-primary">

                    Guardar registro

                </button>

            </div>

        </form>

    </dialog>

@endif

{{-- =====================================================
     EDICIÓN DE PRODUCTOS Y CLIENTES
     EXCLUSIVA DEL DUEÑO
     ===================================================== --}}

@if($esDueno && (!$esInventario || $pestana === 'catalogo'))

    @foreach($registros as $r)

        <dialog id="editar-{{ $r->{$cfg['id']} }}"
                class="cat-modal"
                aria-labelledby="editar-titulo-{{ $r->{$cfg['id']} }}">

            <form method="POST"
                  action="{{ route('catalogos.update', [
                      $tipo,
                      $r->{$cfg['id']}
                  ]) }}">

                @csrf

                @method('PUT')

                <h2 id="editar-titulo-{{ $r->{$cfg['id']} }}">

                    Editar {{ strtolower($cfg['singular']) }}

                </h2>

                <label>

                    Nombre *

                    <input name="nombre"
                           value="{{ $r->{$cfg['nombre']} }}"
                           maxlength="{{ $esInventario ? 100 : 150 }}"
                           required>

                </label>

                @unless($esInventario)

                    <label>

                        Teléfono *

                        <input name="telefono"
                               type="tel"
                               maxlength="20"
                               value="{{ $r->telefono }}"
                               required>

                    </label>

                    <label>

                        Plazo de crédito en días

                        <input name="plazo"
                               type="number"
                               min="1"
                               max="3650"
                               value="{{ $r->plazo_credito_dias }}"
                               required>

                    </label>

                @endunless

                <div class="cat-modal-actions">

                    <button type="button"
                            class="cat-secondary"
                            onclick="this.closest('dialog').close()">

                        Cancelar

                    </button>

                    <button type="submit" class="cat-primary">

                        Guardar cambios

                    </button>

                </div>

            </form>

        </dialog>

    @endforeach

@endif

@endsection