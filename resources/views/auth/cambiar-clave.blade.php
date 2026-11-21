@extends('layouts.app')

@section('titulo', 'LEDGERA - Cambiar clave')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/cambiar-clave.css') }}?v={{ filemtime(public_path('css/cambiar-clave.css')) }}">
@endpush

@section('contenido')
    <div class="brand-header">
        <h1 class="brand-title">LEDGERA</h1>
    </div>

    <div class="login-wrapper">
        <form class="form" id="claveForm" autocomplete="off">
            <div class="title">
                Cambia tu clave<br><span id="subtitulo">crea una clave solo tuya</span>
            </div>

            <p class="aviso" id="avisoTemporal" style="display:none;">
                <i class="fas fa-triangle-exclamation"></i>
                Tu clave es temporal. Crea una propia para continuar.
            </p>

            <div class="campo">
                <input type="password" id="claveActual" class="input" placeholder="Clave actual"
                       autocomplete="current-password" required />
                <button type="button" class="ver" data-ver="claveActual" aria-label="Ver u ocultar clave">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <div class="campo">
                <input type="password" id="claveNueva" class="input" placeholder="Clave nueva"
                       autocomplete="new-password" required />
                <button type="button" class="ver" data-ver="claveNueva" aria-label="Ver u ocultar clave">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <div class="campo">
                <input type="password" id="claveConfirmar" class="input" placeholder="Repite la clave nueva"
                       autocomplete="new-password" required />
                <button type="button" class="ver" data-ver="claveConfirmar" aria-label="Ver u ocultar clave">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <ul class="reglas">
                <li id="regLen"><i class="far fa-circle"></i> Mínimo 8 caracteres</li>
                <li id="regMix"><i class="far fa-circle"></i> Al menos una letra y un número</li>
                <li id="regDist"><i class="far fa-circle"></i> Distinta a la actual</li>
                <li id="regIgual"><i class="far fa-circle"></i> Las dos claves nuevas coinciden</li>
            </ul>

            <div class="message-container">
                <span class="error-message" id="claveError"></span>
                <span class="success-message" id="claveSuccess"></span>
            </div>

            <button type="submit" class="button-confirm ancho">Cambiar clave</button>

            <button type="button" class="link-sec" id="btnCancelar" style="display:none;">Cancelar</button>
            <button type="button" class="link-sec" id="btnSalir" style="display:none;">Cerrar sesión</button>
        </form>
    </div>

    
@endsection

@push('scripts')
    <script>
        window.LEDGERA.obligatorio = @json($obligatorio);
        window.LEDGERA.destino = @json($destino);
    </script>
    <script src="{{ asset('js/cambiar-clave.js') }}?v={{ filemtime(public_path('js/cambiar-clave.js')) }}"></script>
@endpush
