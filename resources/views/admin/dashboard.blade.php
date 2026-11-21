@extends('layouts.app')

@section('titulo', 'Ledgera - Panel de Administración')
@section('tema', 'tema-admin')

@push('estilos')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/stylesadmin.css') }}?v={{ filemtime(public_path('css/stylesadmin.css')) }}">
@endpush

@section('contenido')
  <!-- Fondos decorativos -->
  <div class="bg-blob bg-blob--1"></div>
  <div class="bg-blob bg-blob--2"></div>
  <div class="bg-blob bg-blob--3"></div>

  <!-- ============ HEADER ============ -->
  <header class="header">
    <!-- Logo (izquierda) -->
    <a href="#" class="logo">
      <span class="logo__texto">Led<span>gera</span></span>
    </a>

    <!-- Perfil (derecha) -->
    <div class="perfil-wrapper">
      <button class="perfil-btn" id="perfilBtn" aria-haspopup="true" aria-expanded="false">
        <span class="nombre" id="nombreUsuario">Admin</span>
        <div class="avatar" id="avatarUsuario">AD</div>
      </button>

      <div class="dropdown" id="dropdown" role="menu">
        <a href="{{ route('clave.form') }}" role="menuitem">
          <svg viewBox="0 0 512 512" aria-hidden="true"><path d="M336 352c97.2 0 176-78.8 176-176S433.2 0 336 0S160 78.8 160 176c0 18.7 2.9 36.8 8.3 53.7L7 391c-4.5 4.5-7 10.6-7 17v80c0 13.3 10.7 24 24 24h80c13.3 0 24-10.7 24-24V448h40c13.3 0 24-10.7 24-24V384h40c6.4 0 12.5-2.5 17-7l33.3-33.3c16.9 5.4 35 8.3 53.7 8.3zM376 96a40 40 0 1 1 0 80 40 40 0 1 1 0-80z"/></svg>
          Cambiar contraseña
        </a>
        <a href="#" class="cerrar" role="menuitem">
          <svg viewBox="0 0 512 512" aria-hidden="true"><path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"/></svg>
          Cerrar sesión
        </a>
      </div>
    </div>
  </header>

  <!-- ============ MAIN ============ -->
  <main class="main">
    <div class="saludo-wrapper">
      <span class="saludo-badge">ADMIN</span>
      <h1 class="saludo" id="saludo"></h1>
    </div>

    <!-- Cards interactivas (estilo abanico) -->
    <section class="cards-container">
      <div class="card" data-texto="Usuarios" data-modulo="usuarios">
        <div class="card__icono">
          <svg viewBox="0 0 640 512" aria-hidden="true"><path d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192h42.7c15.9 0 31 3.5 44.6 9.7c-1.3 7.2-1.9 14.7-1.9 22.3c0 38.2 16.8 72.5 43.3 96c-.2 0-.4 0-.7 0H21.3C9.6 320 0 310.4 0 298.7zM405.3 320c-.2 0-.4 0-.7 0c26.6-23.5 43.3-57.8 43.3-96c0-7.6-.7-15-1.9-22.3c13.6-6.3 28.7-9.7 44.6-9.7h42.7C592.2 192 640 239.8 640 298.7c0 11.6-9.6 21.3-21.3 21.3H405.3zM224 224a96 96 0 1 1 192 0 96 96 0 1 1 -192 0zM128 485.3C128 411.7 187.7 352 261.3 352H378.7C452.3 352 512 411.7 512 485.3c0 14.7-11.9 26.7-26.7 26.7H154.7c-14.7 0-26.7-11.9-26.7-26.7z"/></svg>
        </div>
        <span class="card__texto">Gestión de usuarios</span>
    </div>

    <div class="card" data-texto="Puestos" data-modulo="puestos">
        <div class="card__icono">
        <svg viewBox="0 0 384 512" aria-hidden="true"><path d="M48 0C21.5 0 0 21.5 0 48V464c0 26.5 21.5 48 48 48h96V432c0-26.5 21.5-48 48-48s48 21.5 48 48v80h96c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48H48zM64 240c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V240zm112-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V240c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V240zM80 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16zm80 16c0-8.8 7.2-16 16-16h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H176c-8.8 0-16-7.2-16-16V112zM272 96h32c8.8 0 16 7.2 16 16v32c0 8.8-7.2 16-16 16H272c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16z"/></svg>
        </div>
        <span class="card__texto">Gestión de puestos</span>
    </div>

    <div class="card" data-texto="Auditoría" data-modulo="auditoria">
        <div class="card__icono">
        <svg viewBox="0 0 512 512" aria-hidden="true"><path d="M256 0c4.6 0 9.2 1 13.4 2.9L457.7 82.8c22 9.3 38.4 31 38.3 57.2c-.5 99.2-41.3 280.7-213.6 363.2c-16.7 8-36.1 8-52.8 0C57.3 420.7 16.5 239.2 16 140c-.1-26.2 16.3-47.9 38.3-57.2L242.7 2.9C246.8 1 251.4 0 256 0zm0 66.8l0 378.1C394 378 431.1 230.1 432 141.4L256 66.8s0 0 0 0z"/></svg>
        </div>
        <span class="card__texto">Auditoría y soporte</span>
    </div>

    <div class="card" data-texto="Reportes" data-modulo="reportes">
        <div class="card__icono">
        <svg viewBox="0 0 448 512" aria-hidden="true"><path d="M160 80c0-26.5 21.5-48 48-48h32c26.5 0 48 21.5 48 48V432c0 26.5-21.5 48-48 48H208c-26.5 0-48-21.5-48-48V80zM0 272c0-26.5 21.5-48 48-48h32c26.5 0 48 21.5 48 48V432c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V272zM368 96h32c26.5 0 48 21.5 48 48V432c0 26.5-21.5 48-48 48H368c-26.5 0-48-21.5-48-48V144c0-26.5 21.5-48 48-48z"/></svg>
        </div>
        <span class="card__texto">Reportes e indicadores</span>
    </div>
    </section>

<!-- ============ FOOTER ============ -->
<footer class="footer">
    <div>© 2026 <strong>Ledgera</strong> · Panel de Administración · Todos los derechos reservados.</div>
    <div class="footer__links">
    <a href="#">Términos y condiciones</a>
    <a href="#">Política de privacidad</a>
    <a href="#">Soporte técnico</a>
    </div>
</footer>
@endsection

@push('scripts')
    <script src="{{ asset('js/scriptadmin.js') }}?v={{ filemtime(public_path('js/scriptadmin.js')) }}"></script>
@endpush
