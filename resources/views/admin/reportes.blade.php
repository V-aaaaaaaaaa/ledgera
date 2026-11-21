@extends('layouts.app')

@section('titulo', 'LEDGERA - Reportes e indicadores')
@section('tema', 'tema-reportes')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/reportes.css') }}?v={{ filemtime(public_path('css/reportes.css')) }}">
@endpush

@section('contenido')
    <header class="app-header">
        <button class="btn-back" id="btnBack" title="Volver al panel">
            <i class="fas fa-arrow-left"></i>
        </button>

        <h1 class="header-title">Reportes e indicadores</h1>

        <div class="header-user">
            <span id="headerUserName">Admin</span>
            <i class="fas fa-user-circle"></i>
        </div>
    </header>

    <main class="app-main">
        <div class="section-bar">
            <h2 class="section-title">Estado del sistema</h2>
            <button type="button" class="btn-actualizar" id="btnActualizar"><i class="fas fa-rotate"></i> Actualizar</button>
        </div>

        <p class="reporte-nota">
            Indicadores de Ledgera como sistema: puestos, cuentas, seguridad y actividad.
            No incluye ventas, inventario ni caja de los puestos.
            <span id="generado"></span>
        </p>

        <h3 class="titulo-seccion">Puestos</h3>
        <div class="grid-indicadores">
            <div class="indicador"><span class="numero" data-ind="puestos.activos">-</span><span class="etiqueta">Puestos activos</span></div>
            <div class="indicador"><span class="numero" data-ind="puestos.inactivos">-</span><span class="etiqueta">Puestos inactivos</span></div>
            <div class="indicador" data-alerta="ambar"><span class="numero" data-ind="puestos.sin_dueno">-</span><span class="etiqueta">Activos sin Dueño</span></div>
        </div>

        <h3 class="titulo-seccion">Cuentas activas</h3>
        <div class="grid-indicadores">
            <div class="indicador"><span class="numero" data-ind="cuentas.administradores">-</span><span class="etiqueta">Administradores</span></div>
            <div class="indicador"><span class="numero" data-ind="cuentas.duenos">-</span><span class="etiqueta">Dueños</span></div>
            <div class="indicador"><span class="numero" data-ind="cuentas.vendedores">-</span><span class="etiqueta">Vendedores</span></div>
            <div class="indicador"><span class="numero" data-ind="cuentas.inactivas">-</span><span class="etiqueta">Cuentas inactivas</span></div>
        </div>

        <h3 class="titulo-seccion">Seguridad</h3>
        <div class="grid-indicadores">
            <div class="indicador" data-alerta="rojo"><span class="numero" data-ind="seguridad.bloqueadas_ahora">-</span><span class="etiqueta">Bloqueadas ahora</span></div>
            <div class="indicador" data-alerta="ambar"><span class="numero" data-ind="seguridad.clave_temporal">-</span><span class="etiqueta">Con clave temporal pendiente</span></div>
            <div class="indicador" data-alerta="ambar"><span class="numero" data-ind="seguridad.sin_puesto">-</span><span class="etiqueta">Sin puesto asignado</span></div>
        </div>

        <h3 class="titulo-seccion">Soporte</h3>
        <div class="grid-indicadores">
            <div class="indicador" data-alerta="ambar"><span class="numero" data-ind="incidencias.pendientes">-</span><span class="etiqueta">Incidencias pendientes</span></div>
            <div class="indicador" data-alerta="rojo"><span class="numero" data-ind="incidencias.altas">-</span><span class="etiqueta">Pendientes de impacto alto</span></div>
        </div>

        <h3 class="titulo-seccion">Actividad (últimos 7 días)</h3>
        <p class="reporte-nota" id="sinAuditoria" style="display:none;">
            Aún no hay datos de actividad. Crea la tabla de auditoría con el archivo <strong>database/sql/001_auditoria.sql</strong>.
        </p>
        <div id="bloqueActividad">
            <div class="grid-indicadores">
                <div class="indicador"><span class="numero" data-ind="actividad.ingresos_hoy">-</span><span class="etiqueta">Ingresos de hoy</span></div>
                <div class="indicador" data-alerta="rojo"><span class="numero" data-ind="actividad.bloqueos_7">-</span><span class="etiqueta">Bloqueos en 7 días</span></div>
                <div class="indicador"><span class="numero" data-ind="actividad.acciones_7">-</span><span class="etiqueta">Cambios administrativos</span></div>
            </div>

            <div class="grafica">
                <p class="grafica-titulo">Ingresos por día</p>
                <div class="barras" id="barras"></div>
            </div>

            <h3 class="titulo-seccion">Últimos movimientos</h3>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Hecha por</th>
                            <th>Cuenta afectada</th>
                        </tr>
                    </thead>
                    <tbody id="movimientosBody"></tbody>
                </table>
                <div class="empty-state" id="emptyState" style="display:none;">
                    <i class="fas fa-clipboard-list"></i>
                    <p>Todavía no hay movimientos registrados.</p>
                </div>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/reportes.js') }}?v={{ filemtime(public_path('js/reportes.js')) }}"></script>
@endpush
