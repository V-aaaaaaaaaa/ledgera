@extends('layouts.app')

@push('estilos')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/operativo.css') }}?v={{ filemtime(public_path('css/operativo.css')) }}">
@endpush

@section('contenido')

@php
    $usuario = auth()->user();
    $esDueno = $usuario->rol === 'Dueno';
@endphp

<div class="op-shell {{ request()->routeIs('dashboard') ? 'op-home' : '' }}" id="opShell">

    {{-- Barra móvil --}}
    <div class="op-mobile-bar">
        <button type="button" id="opMobileToggle"
                class="op-icon-button"
                aria-label="Abrir menú"
                aria-controls="opSidebar"
                aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>

        <a href="{{ route('dashboard') }}" class="op-brand">
            Led<span>gera</span>
        </a>

        <i class="fas fa-user-circle"></i>
    </div>

    <button type="button" class="op-overlay" id="opOverlay"
            aria-label="Cerrar menú" hidden>
    </button>

    {{-- Panel lateral --}}
    <aside class="op-sidebar" id="opSidebar">

        <div class="op-brand-row">

            <a href="{{ route('dashboard') }}" class="op-brand">
                Led<span>gera</span>
            </a>

            <button type="button" class="op-icon-button"
                    id="opCollapse"
                    aria-label="Contraer menú"
                    aria-expanded="true">
                <i class="fas fa-bars-staggered"></i>
            </button>

            <button type="button"
                    class="op-icon-button op-mobile-close"
                    id="opMobileClose"
                    aria-label="Cerrar menú">
                <i class="fas fa-times"></i>
            </button>

        </div>

        {{-- Información del negocio --}}
        <div class="op-business">
            <div class="op-business-icon">
                <i class="fas fa-store"></i>
            </div>

            <div class="op-business-info">
                <small>Mi negocio</small>
                <strong>{{ $usuario->puesto?->nombre_puesto }}</strong>
            </div>
        </div>

        {{-- Navegación --}}
        <nav class="op-navigation" aria-label="Menú principal">

            <p class="op-section-label">PRINCIPAL</p>

            <a href="{{ route('dashboard') }}"
               class="op-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}"
               title="Inicio" aria-label="Inicio">
                <i class="fas fa-house"></i>
                <span>Inicio</span>
            </a>

            <a href="{{ route('cierre.index') }}"
               class="op-link {{ request()->routeIs('cierre.*') ? 'is-active' : '' }}"
               title="Mi caja" aria-label="Mi caja">
                <i class="fas fa-cash-register"></i>
                <span>Mi caja</span>
            </a>

            <p class="op-section-label">CATÁLOGOS</p>

                <a href="{{ route('catalogos.index', 'clientes') }}"
                class="op-link {{ request()->route('tipo') === 'clientes' ? 'is-active' : '' }}"
                title="Clientes" aria-label="Clientes">
                    <i class="fas fa-address-book"></i>
                    <span>Clientes</span>
                </a>


            @if($usuario->puede('gestionar_proveedores'))
                <p class="op-section-label">GESTIÓN DEL NEGOCIO</p>

                <a href="{{ route('proveedores.index') }}"
                   class="op-link {{ request()->routeIs('proveedores.*') ? 'is-active' : '' }}"
                   title="Proveedores" aria-label="Proveedores">
                    <i class="fas fa-truck"></i>
                    <span>Proveedores</span>
                </a>
            @endif

            @if($esDueno)

                <a href="{{ route('actividad.index') }}"
                   class="op-link {{ request()->routeIs('actividad.*') ? 'is-active' : '' }}"
                   title="Actividad del negocio" aria-label="Actividad del negocio">
                    <i class="fas fa-list-check"></i>
                    <span>Actividad del negocio</span>
                </a>

                <a href="{{ route('gestion.usuarios') }}"
                   class="op-link {{ request()->routeIs('gestion.usuarios*') ? 'is-active' : '' }}"
                   title="Mi equipo" aria-label="Mi equipo">
                    <i class="fas fa-users"></i>
                    <span>Mi equipo</span>
                </a>

            @endif

        </nav>

        {{-- Información del usuario --}}
        <div class="op-account">

            <div class="op-avatar {{ $esDueno ? 'avatar-dueno' : 'avatar-vendedor' }}">
                <i class="fas {{ $esDueno ? 'fa-store' : 'fa-user' }}"></i>
            </div>

            <div class="op-account-info">
                <strong>{{ $usuario->nombre_completo }}</strong>
                <small>{{ $esDueno ? 'Dueño del negocio' : 'Vendedor' }}</small>
            </div>

        </div>

        {{-- Acciones de cuenta --}}
        <div class="op-account-actions">

            <button type="button"
                    class="op-link"
                    data-reportar
                    title="Reportar un problema"
                    aria-label="Reportar un problema">
                <i class="fas fa-circle-exclamation"></i>
                <span>Reportar un problema</span>
            </button>

            <a href="{{ route('clave.form') }}"
               class="op-link"
               title="Cambiar contraseña"
               aria-label="Cambiar contraseña">
                <i class="fas fa-key"></i>
                <span>Cambiar contraseña</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit"
                        class="op-link op-logout"
                        title="Cerrar sesión"
                        aria-label="Cerrar sesión">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Cerrar sesión</span>
                </button>
            </form>

        </div>

    </aside>

    {{-- Contenido dinámico de cada módulo --}}
    <div class="op-content">
        @yield('operativo-contenido')
    </div>

</div>

{{-- Ventana "Reportar un problema": disponible en todas las pantallas del Dueño y el Vendedor --}}
  <div class="modal-reporte" id="modalReporte" hidden>
    <div class="modal-reporte__caja" role="dialog" aria-modal="true" aria-labelledby="tituloReporte">
      <div class="modal-reporte__cab">
        <h2 id="tituloReporte">Reportar un problema</h2>
        <button type="button" class="modal-reporte__cerrar" id="reporteCerrar" aria-label="Cerrar">&times;</button>
      </div>

      <form id="reporteForm" novalidate>
        <label for="repModulo">¿En qué parte pasó?</label>
        <select id="repModulo">
          <option value="">Elige una opción</option>
          @foreach (\App\Models\Incidencia::MODULOS as $modulo)
            <option value="{{ $modulo }}">{{ $modulo }}</option>
          @endforeach
        </select>

        <p class="rep-etiqueta">¿Qué tan grave es?</p>
        <div class="rep-impactos" id="repImpactos">
          <button type="button" class="rep-impacto" data-impacto="Alto"><strong>No puedo trabajar</strong><span>Me impide vender o registrar</span></button>
          <button type="button" class="rep-impacto" data-impacto="Medio"><strong>Me estorba</strong><span>Puedo seguir, pero con problemas</span></button>
          <button type="button" class="rep-impacto" data-impacto="Bajo"><strong>Es una molestia</strong><span>Algo pequeño o por mejorar</span></button>
        </div>

        <label for="repDescripcion">Cuéntanos qué pasó</label>
        <textarea id="repDescripcion" rows="4" maxlength="500" placeholder="Ej: al registrar una venta a fiado se queda cargando"></textarea>
        <small id="repContador">0 / 500</small>

        <p class="rep-msg" id="repMsg" role="alert" hidden></p>
        <button type="submit" class="rep-enviar" id="repEnviar">Enviar reporte</button>
      </form>
    </div>
  </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/operativo.js') }}?v={{ filemtime(public_path('js/operativo.js')) }}"></script>
@endpush

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/incidencia.css') }}?v={{ filemtime(public_path('css/incidencia.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/incidencia-reporte.js') }}?v={{ filemtime(public_path('js/incidencia-reporte.js')) }}"></script>
@endpush