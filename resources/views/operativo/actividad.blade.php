@extends('layouts.operativo')

@section('titulo', 'LEDGERA - Actividad del negocio')
@section('tema', 'tema-auditoria')

@push('estilos')
    {{-- Encabezado compartido con las demás pantallas --}}
    <link rel="stylesheet" href="{{ asset('css/usuarios.css') }}?v={{ filemtime(public_path('css/usuarios.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/actividad.css') }}?v={{ filemtime(public_path('css/actividad.css')) }}">
@endpush

@section('operativo-contenido')
    <header class="app-header">
        <button class="btn-back" id="btnBack" title="Volver al inicio">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="header-title">Actividad del negocio</h1>
        <div class="header-user">
            <span id="headerUserName"></span>
            <i class="fas fa-user-circle"></i>
        </div>
    </header>

    <main class="app-main act">
        <p class="act__intro">Todo lo que pasó en tu puesto: ventas, abonos, gastos, mercancía, caja y cambios. Solo consulta, no se modifica nada.</p>

        {{-- Filtros --}}
        <section class="act__filtros">
            <div class="act__atajos" id="atajos">
                <button type="button" data-dias="0" class="activo">Hoy</button>
                <button type="button" data-dias="1">Ayer</button>
                <button type="button" data-dias="7">7 días</button>
                <button type="button" data-dias="30">30 días</button>
            </div>

            <div class="act__fila">
                <label>Desde <input type="date" id="desde"></label>
                <label>Hasta <input type="date" id="hasta"></label>
                <label>¿Quién?
                    <select id="usuario"><option value="">Todos</option></select>
                </label>
            </div>

            <div class="act__tipos" id="tipos">
                @foreach ($tipos as $t)
                    <button type="button" data-tipo="{{ $t }}">{{ $t }}</button>
                @endforeach
            </div>

            <div class="act__fila">
                <input type="search" id="buscar" placeholder="Buscar: cliente, proveedor, producto, nota…" maxlength="100">
                <button type="button" class="act__limpiar" id="btnLimpiar">Limpiar filtros</button>
            </div>
        </section>

        {{-- Resumen --}}
        <section class="act__resumen" id="resumen"></section>

        {{-- Lista --}}
        <p class="act__msg" id="msg" role="alert" hidden></p>
        <ul class="act__lista" id="lista"></ul>
        <p class="act__vacio" id="vacio" hidden>No hay movimientos con estos filtros.</p>
        <button type="button" class="act__mas" id="btnMas" hidden>Ver más</button>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/actividad.js') }}?v={{ filemtime(public_path('js/actividad.js')) }}"></script>
@endpush
