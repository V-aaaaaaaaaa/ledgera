@extends('layouts.operativo')

@section('titulo', 'LEDGERA - Cierre de caja')
@section('tema', 'tema-cierre')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    {{-- Encabezado compartido con las demás pantallas --}}
    <link rel="stylesheet" href="{{ asset('css/usuarios.css') }}?v={{ filemtime(public_path('css/usuarios.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/cierre.css') }}?v={{ filemtime(public_path('css/cierre.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/cierre-puesto.css') }}?v={{ filemtime(public_path('css/cierre-puesto.css')) }}">
@endpush

@section('operativo-contenido')
    <header class="app-header">
        <button class="btn-back" id="btnBack" title="Volver al inicio">
            <i class="fas fa-arrow-left"></i>
        </button>
        <h1 class="header-title">Cierre de caja</h1>
        <div class="header-user">
            <span id="headerUserName"></span>
            <i class="fas fa-user-circle"></i>
        </div>
    </header>

    <main class="app-main cierre">
        <p class="cierre__cargando" id="cargando">Cargando…</p>

        {{-- Caja cerrada --}}
        <section class="cierre__vacio" id="sinCaja" hidden>
            <i class="fas fa-lock"></i>
            <h2>La caja está cerrada</h2>
            <p>Ábrela desde el inicio para empezar la jornada.</p>
            <button type="button" class="cierre__btn" data-volver>Ir al inicio</button>
        </section>

        {{-- Formulario de cierre --}}
        <section id="conCaja" hidden>
            <p class="cierre__jornada" id="infoJornada"></p>

            <div class="cierre__tarjeta">
                <h2>Efectivo que debería haber</h2>
                <dl class="cierre__lista">
                    <div><dt>Base inicial</dt><dd id="cInicial"></dd></div>
                    <div><dt>+ Ventas en efectivo</dt><dd id="cVentas"></dd></div>
                    <div><dt>+ Abonos de fiado en efectivo</dt><dd id="cAbonos"></dd></div>
                    <div id="filaReversiones"><dt>− Abonos corregidos</dt><dd id="cReversiones"></dd></div>
                    <div><dt>− Gastos y pagos en efectivo</dt><dd id="cEgresos"></dd></div>
                </dl>
                <div class="cierre__total">
                    <span>Efectivo esperado</span>
                    <strong id="cEsperado"></strong>
                </div>
            </div>

            <details class="cierre__tarjeta cierre__digital">
                <summary>Pagos digitales y fiado <small>(no están en el cajón)</small></summary>
                <dl class="cierre__lista">
                    <div><dt>Ventas por Nequi, Daviplata o Bre-B</dt><dd id="dVentas"></dd></div>
                    <div><dt>Abonos digitales</dt><dd id="dAbonos"></dd></div>
                    <div><dt>Gastos pagados digital</dt><dd id="dEgresos"></dd></div>
                    <div><dt>Vendido a fiado</dt><dd id="dCredito"></dd></div>
                </dl>
            </details>

            <div class="cierre__tarjeta">
                <label for="contado"><h2>¿Cuánto efectivo contaste?</h2></label>
                <div class="cierre__monto">
                    <span>$</span>
                    <input type="text" id="contado" inputmode="numeric" autocomplete="off" placeholder="0">
                </div>

                <p class="cierre__dif" id="diferencia" hidden></p>

                <div id="bloqueExplicacion" hidden>
                    <label for="explicacion">¿Qué pasó? <small>(obligatorio: la diferencia supera el límite)</small></label>
                    <textarea id="explicacion" rows="3" maxlength="500" placeholder="Ej: le di vueltas de más a un cliente"></textarea>
                </div>

                <p class="cierre__limite">
                    Límite de descuadre del puesto: <strong id="umbral"></strong>
                    <button type="button" class="cierre__link" id="btnUmbral" hidden>Cambiar</button>
                </p>

                <p class="cierre__msg" id="msg" role="alert" hidden></p>
                <button type="button" class="cierre__btn" id="btnCerrar">Cerrar caja</button>
            </div>
        </section>

        {{-- Resultado --}}
        <section class="cierre__vacio cierre__listo" id="listo" hidden>
            <i class="fas fa-circle-check"></i>
            <h2>Caja cerrada</h2>
            <p id="resultado"></p>
            <button type="button" class="cierre__btn" data-volver>Volver al inicio</button>
        </section>
        {{-- ===== Cierre del puesto (consolida todas las cajas) ===== --}}
        <section class="cierre__tarjeta cpu" id="cierrePuesto" hidden>
            <h2><i class="fas fa-store"></i> Cierre del puesto</h2>
            <p class="cpu__ayuda">Cuando todas las personas hayan cerrado su caja, cualquiera puede hacer el cierre del puesto: suma todas las cajas en un solo cierre del día.</p>

            <div class="cpu__aviso" id="cpuAbiertas" hidden></div>

            <ul class="cpu__lista" id="cpuLista"></ul>
            <div class="cpu__total" id="cpuTotal" hidden></div>

            <p class="cierre__msg" id="cpuMsg" role="alert" hidden></p>
            <button type="button" class="cierre__btn" id="btnCierrePuesto" hidden>Hacer cierre del puesto</button>

            <p class="cpu__ultimo" id="cpuUltimo" hidden></p>
        </section>
    </main>

    {{-- Confirmación (el cierre no se puede deshacer) --}}
    <div class="cierre-modal" id="modalConfirmar" hidden>
        <div class="cierre-modal__caja" role="dialog" aria-modal="true">
            <h2>¿Cerrar la caja?</h2>
            <p id="textoConfirmar"></p>
            <p><strong>Después de cerrar no se puede modificar.</strong></p>
            <div class="cierre-modal__botones">
                <button type="button" class="cierre__btn cierre__btn--sec" id="noConfirmar">Revisar</button>
                <button type="button" class="cierre__btn" id="siConfirmar">Sí, cerrar</button>
            </div>
        </div>
    </div>

    {{-- Límite de descuadre (solo Dueño) --}}
    <div class="cierre-modal" id="modalUmbral" hidden>
        <div class="cierre-modal__caja" role="dialog" aria-modal="true">
            <h2>Límite de descuadre</h2>
            <p>Si la diferencia del cierre es mayor a este valor, se pedirá una explicación.</p>
            <div class="cierre__monto">
                <span>$</span>
                <input type="text" id="nuevoUmbral" inputmode="numeric" autocomplete="off">
            </div>
            <p class="cierre__msg" id="msgUmbral" role="alert" hidden></p>
            <div class="cierre-modal__botones">
                <button type="button" class="cierre__btn cierre__btn--sec" id="cancelarUmbral">Cancelar</button>
                <button type="button" class="cierre__btn" id="guardarUmbral">Guardar</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/cierre.js') }}?v={{ filemtime(public_path('js/cierre.js')) }}"></script>
    <script src="{{ asset('js/cierre-puesto.js') }}?v={{ filemtime(public_path('js/cierre-puesto.js')) }}"></script>
@endpush
