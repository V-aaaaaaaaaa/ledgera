@extends('layouts.operativo')

@section('titulo', 'LEDGERA - Egresos')
@section('tema', 'tema-egresos')

@push('estilos')
    {{-- Encabezado compartido con las demás pantallas --}}
    <link rel="stylesheet" href="{{ asset('css/usuarios.css') }}?v={{ filemtime(public_path('css/usuarios.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/egresos.css') }}?v={{ filemtime(public_path('css/egresos.css')) }}">
@endpush

@section('operativo-contenido')
    <header class="app-header">
        <button class="btn-back" id="btnBack" title="Volver al inicio">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="header-title">Egresos</h1>
        <div class="header-user">
            <span id="headerUserName"></span>
            <i class="fas fa-user-circle"></i>
        </div>
    </header>

    <main class="app-main egr">
        <p class="egr__cargando" id="cargando">Cargando…</p>

        {{-- Caja cerrada --}}
        <section class="egr__vacio" id="sinCaja" hidden>
            <i class="fas fa-lock"></i>
            <h2>La caja está cerrada</h2>
            <p>Ábrela desde el inicio para registrar gastos y pagos.</p>
            <button type="button" class="egr__btn" data-volver>Ir al inicio</button>
        </section>

        <section id="conCaja" hidden>
            {{-- Totales de hoy --}}
            <div class="egr__totales">
                <div><span>Salió en efectivo</span><strong id="totEfectivo">$0</strong></div>
                <div><span>Salió digital</span><strong id="totDigital">$0</strong></div>
            </div>

            {{-- Formulario --}}
            <div class="egr__tarjeta">
                <h2>Registrar egreso</h2>

                <p class="egr__etq">¿Qué tipo de salida es?</p>
                <div class="egr__opciones" id="categorias">
                    <button type="button" data-cat="Gasto_urgencia" class="activo"><i class="fas fa-bolt"></i> Gasto de urgencia</button>
                    <button type="button" data-cat="Otros"><i class="fas fa-ellipsis"></i> Otros</button>
                    <button type="button" data-cat="Pago_proveedor" id="optPago" hidden><i class="fas fa-truck"></i> Pago a proveedor</button>
                </div>

                {{-- Solo para pago a proveedor --}}
                <div id="bloquePago" hidden>
                    <label class="egr__etq" for="proveedor">Proveedor</label>
                    <select id="proveedor" class="egr__campo"></select>
                    <p class="egr__aviso" id="sinDeudas" hidden>No le debes a ningún proveedor en este momento.</p>

                    <div id="facturas" class="egr__facturas"></div>
                    <p class="egr__seleccion" id="totalSeleccion" hidden></p>
                </div>

                <label class="egr__etq" for="monto">¿Cuánto?</label>
                <div class="egr__monto">
                    <span>$</span>
                    <input type="text" id="monto" inputmode="numeric" autocomplete="off" placeholder="0">
                </div>

                <p class="egr__etq">¿Cómo se pagó?</p>
                <div class="egr__opciones egr__opciones--medio" id="medios">
                    <button type="button" data-medio="Efectivo" class="activo">Efectivo</button>
                    <button type="button" data-medio="Nequi">Nequi</button>
                    <button type="button" data-medio="Daviplata">Daviplata</button>
                    <button type="button" data-medio="Bre-B">Bre-B</button>
                </div>

                <label class="egr__etq" for="nota" id="etqNota">¿En qué se gastó? <small>(obligatorio)</small></label>
                <textarea id="nota" class="egr__campo" rows="2" maxlength="255" placeholder="Ej: descargue del camión de papa"></textarea>

                <label class="egr__etq" for="soporte">N.º de recibo o referencia <small>(opcional)</small></label>
                <input type="text" id="soporte" class="egr__campo" maxlength="255" autocomplete="off" placeholder="Ej: REC-0045">

                <p class="egr__msg" id="msg" role="alert" hidden></p>
                <button type="button" class="egr__btn" id="btnGuardar">Registrar egreso</button>
            </div>

            {{-- Lista de hoy --}}
            <div class="egr__tarjeta">
                <h2>Egresos de esta jornada</h2>
                <ul class="egr__lista" id="lista"></ul>
                <p class="egr__vacio-lista" id="listaVacia">Todavía no hay egresos en esta jornada.</p>
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/egresos.js') }}?v={{ filemtime(public_path('js/egresos.js')) }}"></script>
@endpush
