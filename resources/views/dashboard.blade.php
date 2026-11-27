@extends('layouts.operativo')

@section('titulo', 'Ledgera · Dashboard')
@section('tema', 'tema-operativo')

@push('estilos')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
@endpush

@section('operativo-contenido')
  <!-- Fondos decorativos -->
  <div class="bg-blob bg-blob--1"></div>
  <div class="bg-blob bg-blob--2"></div>
  <div class="bg-blob bg-blob--3"></div>

  <header class="header">
    <!-- Logo (izquierda) -->
    <a href="#" class="logo">Led<span>gera</span></a>

  </header>

  <!-- ============ MAIN ============ -->
  <main class="main">
    <h1 class="saludo" id="saludo"></h1>

    <!-- Estado de la caja (lo llena js/caja.js) -->
    <div class="caja-estado" id="cajaEstado" hidden></div>

    <!-- Cards -->
    <section class="cards-container">
      <div class="card" data-modulo="ventas">
        <div class="card__icono">
          <svg viewBox="0 0 576 512" aria-hidden="true"><path d="M64 64C28.7 64 0 92.7 0 128V384c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm64 320H64V320c35.3 0 64 28.7 64 64zM64 192V128h64c0 35.3-28.7 64-64 64zM448 384c0-35.3 28.7-64 64-64v64H448zm64-192c-35.3 0-64-28.7-64-64h64v64zM288 160a96 96 0 1 1 0 192 96 96 0 1 1 0-192z"/></svg>
        </div>
        <span class="card__texto">Ventas</span>
      </div>

      <div class="card" data-modulo="inventario">
        <div class="card__icono">
          <svg viewBox="0 0 640 512" aria-hidden="true"><path d="M392.8 1.2c-17-4.9-34.7 5-39.6 22l-128 448c-4.9 17 5 34.7 22 39.6s34.7-5 39.6-22l128-448c4.9-17-5-34.7-22-39.6zm80.6 120.1c-12.5 12.5-12.5 32.8 0 45.3L562.7 256l-89.4 89.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3l-112-112c-12.5-12.5-32.8-12.5-45.3 0zm-306.7 0c-12.5-12.5-32.8-12.5-45.3 0l-112 112c-12.5 12.5-12.5 32.8 0 45.3l112 112c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L77.3 256l89.4-89.4c12.5-12.5 12.5-32.8 0-45.3z"/></svg>
        </div>
        <span class="card__texto">Inventario</span>
      </div>

      <div class="card" data-modulo="fiado">
        <div class="card__icono">
          <svg viewBox="0 0 512 512" aria-hidden="true"><path d="M256 0c4.6 0 9.2 1 13.4 2.9L457.7 82.8c22 9.3 38.4 31 38.3 57.2c-.5 99.2-41.3 280.7-213.6 363.2c-16.7 8-36.1 8-52.8 0C57.3 420.7 16.5 239.2 16 140c-.1-26.2 16.3-47.9 38.3-57.2L242.7 2.9C246.8 1 251.4 0 256 0zm0 66.8l0 378.1C394 378 431.1 230.1 432 141.4L256 66.8s0 0 0 0z"/></svg>
        </div>
        <span class="card__texto">Fiado</span>
      </div>

      <div class="card" data-modulo="egresos">
        <div class="card__icono">
          <svg viewBox="0 0 512 512" aria-hidden="true"><path d="M64 64h160v48H112v288h288V288h48v160H64V64zm224 0h160v160h-48V145L249 312l-34-34L382 111h-94V64z"/></svg>
        </div>
        <span class="card__texto">Egresos</span>
      </div>

      <div class="card" data-modulo="exportaciones" data-solo="Dueno">
        <div class="card__icono">
          <svg viewBox="0 0 512 512" aria-hidden="true"><path d="M156.6 384.9L125.7 354c-8.5-8.5-11.5-20.8-7.7-32.2c3-8.9 9.7-16.3 18.3-20.1L300.2 234c10.8-4.5 23.1-1.7 31.1 6.3l29.4 29.4c8.5 8.5 11.5 20.8 7.7 32.2c-3 8.9-9.7 16.3-18.3 20.1L185.7 388.7c-10.8 4.5-23.1 1.7-31.1-6.3z"/><path d="M383.9 15.2c-4.6-4.6-11.3-6.5-17.7-5.1L130.4 75.3c-4.6 1-8.5 4.1-10.6 8.3L15.4 289.8c-3.3 6.6-1.6 14.6 4.1 19.4l83.1 69.6c5.9 4.9 14.6 4.7 20.2-.6l98.7-92.3c5-4.7 12.5-4.7 17.6-.1l70.4 63.5c5.7 5.1 14.4 4.9 19.8-.5L488.9 172.7c4.6-4.6 6.4-11.3 4.6-17.6L394.6 27.2c-2.6-4.6-6.6-8.3-10.7-12z"/></svg>
        </div>
        <span class="card__texto">Exportaciones</span>
      </div>
    </section>

    <!-- Botón cierre de caja -->
    <section class="cierre-wrapper">
      <button class="btn-cierre" id="btnCierre">
        <svg viewBox="0 0 640 512" aria-hidden="true"><path d="M64 64C28.7 64 0 92.7 0 128V384c0 35.3 28.7 64 64 64H576c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm0 64H576V384H64V128zm96 96c0-17.7 14.3-32 32-32h64c17.7 0 32 14.3 32 32s-14.3 32-32 32H192c-17.7 0-32-14.3-32-32zm160 0c0-17.7 14.3-32 32-32h64c17.7 0 32 14.3 32 32s-14.3 32-32 32H352c-17.7 0-32-14.3-32-32z"/></svg>
        <span id="btnCajaTexto">Cargando caja…</span>
      </button>
    </section>
  </main>

  <footer class="footer">
    <div>© 2026 <strong>Ledgera</strong> · Todos los derechos reservados.</div>
    <div class="footer__links">
      <a href="#">Términos y condiciones</a>
      <a href="#">Política de privacidad</a>
      <a href="#">Soporte</a>
    </div>
  </footer>

  

  <!-- ============ VENTANA: ABRIR CAJA / CORREGIR MONTO (RF-020) ============ -->
  <div class="modal-caja" id="modalCaja" hidden>
    <div class="modal-caja__caja" role="dialog" aria-modal="true" aria-labelledby="cajaTitulo">
      <h2 id="cajaTitulo">Abrir caja</h2>
      <p class="modal-caja__ayuda" id="cajaAyuda">¿Con cuánto efectivo empiezas?</p>

      <div class="modal-caja__monto">
        <span>$</span>
        <input type="text" id="cajaMonto" inputmode="numeric" autocomplete="off" aria-label="Efectivo inicial">
      </div>
      <small class="modal-caja__nota" id="cajaNota"></small>

      <p class="modal-caja__msg" id="cajaMsg" role="alert" hidden></p>

      <div class="modal-caja__botones">
        <button type="button" class="modal-caja__cancelar" id="cajaCancelar">Cancelar</button>
        <button type="button" class="modal-caja__confirmar" id="cajaConfirmar">Confirmar</button>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
    <script src="{{ asset('js/caja.js') }}?v={{ filemtime(public_path('js/caja.js')) }}"></script>
@endpush

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/caja.css') }}?v={{ filemtime(public_path('css/caja.css')) }}">
@endpush
