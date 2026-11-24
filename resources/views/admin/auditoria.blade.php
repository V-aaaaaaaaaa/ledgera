@extends('layouts.app')

@section('titulo', 'LEDGERA - Auditoría y soporte')
@section('tema', 'tema-auditoria')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/auditoria.css') }}?v={{ filemtime(public_path('css/auditoria.css')) }}">
@endpush

@section('contenido')
    <header class="app-header">
        <button class="btn-back" id="btnBack" title="Volver al panel">
            <i class="fas fa-arrow-left"></i>
        </button>

        <h1 class="header-title">Auditoría y soporte</h1>

        <div class="header-user">
            <span id="headerUserName">Admin</span>
            <i class="fas fa-user-circle"></i>
        </div>
    </header>

    <nav class="tabs" id="tabs">
        <button type="button" class="tab activo" data-tab="acciones">
            <i class="fas fa-clipboard-list"></i> Registro de acciones
        </button>
        <button type="button" class="tab" data-tab="incidencias">
            <i class="fas fa-headset"></i> Incidencias
            <span class="contador" id="contadorInc" hidden>0</span>
        </button>
    </nav>

    <main class="app-main">

        {{-- ================= PESTAÑA 1: REGISTRO DE ACCIONES ================= --}}
        <section id="tab-acciones">
            <p class="auditoria-nota">
                Aquí se ve quién hizo qué con las cuentas, los puestos y los accesos.
                No incluye ventas, inventario ni caja de los puestos.
            </p>

            <div class="filtros">
                <label>Desde <input type="date" id="fDesde"></label>
                <label>Hasta <input type="date" id="fHasta"></label>
                <label>Acción
                    <select id="fAccion"><option value="">Todas</option></select>
                </label>
                <label>Buscar registro
                    <input type="search" id="fBuscar"
                        placeholder="Usuario, puesto o detalle"
                        autocomplete="off">
                </label>
                <div class="filtros-botones">
                    <button type="button" class="btn-filtro" id="btnFiltrar"><i class="fas fa-filter"></i> Filtrar</button>
                    <button type="button" class="btn-filtro claro" id="btnLimpiar">Limpiar</button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Acción</th>
                            <th>Hecha por</th>
                            <th>Cuenta afectada</th>
                            <th>Puesto</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody id="auditoriaBody"></tbody>
                </table>

                <div class="empty-state" id="emptyState" style="display:none;">
                    <i class="fas fa-clipboard-list"></i>
                    <p>No hay registros con esos filtros.</p>
                </div>
            </div>

            <div class="paginacion">
                <button type="button" class="btn-filtro claro" id="btnAnterior"><i class="fas fa-chevron-left"></i> Anterior</button>
                <span id="infoPagina"></span>
                <button type="button" class="btn-filtro claro" id="btnSiguiente">Siguiente <i class="fas fa-chevron-right"></i></button>
            </div>
        </section>

        {{-- ================= PESTAÑA 2: INCIDENCIAS ================= --}}
        <section id="tab-incidencias" hidden>
            <p class="auditoria-nota">
                Problemas que reportan el Dueño y el Vendedor. Cambia el estado a medida que los atiendes.
            </p>

            <div class="resumen-inc" id="resumenInc"></div>

            <div class="filtros">
                <label>Estado
                    <select id="iEstado">
                        <option value="pendientes">Pendientes</option>
                        <option value="Abierta">Abiertas</option>
                        <option value="En proceso">En proceso</option>
                        <option value="Resuelta">Resueltas</option>
                        <option value="todas">Todas</option>
                    </select>
                </label>
                <label>Impacto
                    <select id="iImpacto">
                        <option value="">Todos</option>
                        <option value="Alto">Alto</option>
                        <option value="Medio">Medio</option>
                        <option value="Bajo">Bajo</option>
                    </select>
                </label>
            </div>

            <p class="inc-mensaje" id="iMensaje" hidden></p>
            <div class="inc-grid" id="listaIncidencias"></div>

            <div class="empty-state" id="iVacio" style="display:none;">
                <i class="fas fa-circle-check"></i>
                <p>No hay incidencias con esos filtros.</p>
            </div>

            <div class="paginacion">
                <button type="button" class="btn-filtro claro" id="iAnterior"><i class="fas fa-chevron-left"></i> Anterior</button>
                <span id="iInfoPagina"></span>
                <button type="button" class="btn-filtro claro" id="iSiguiente">Siguiente <i class="fas fa-chevron-right"></i></button>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/auditoria.js') }}?v={{ filemtime(public_path('js/auditoria.js')) }}"></script>
    <script src="{{ asset('js/incidencias.js') }}?v={{ filemtime(public_path('js/incidencias.js')) }}"></script>
@endpush
