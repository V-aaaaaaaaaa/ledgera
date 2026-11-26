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

                <a href="{{ route('catalogos.index', 'productos') }}"
                class="op-link {{ request()->route('tipo') === 'productos' ? 'is-active' : '' }}"
                title="Productos" aria-label="Productos">
                    <i class="fas fa-boxes-stacked"></i>
                    <span>Productos</span>
                </a>

            @if($esDueno)

                <p class="op-section-label">GESTIÓN DEL NEGOCIO</p>

                <a href="{{ route('proveedores.index') }}"
                   class="op-link {{ request()->routeIs('proveedores.*') ? 'is-active' : '' }}"
                   title="Proveedores" aria-label="Proveedores">
                    <i class="fas fa-truck"></i>
                    <span>Proveedores</span>
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

@endsection

@push('scripts')
    <script src="{{ asset('js/operativo.js') }}?v={{ filemtime(public_path('js/operativo.js')) }}"></script>
@endpush