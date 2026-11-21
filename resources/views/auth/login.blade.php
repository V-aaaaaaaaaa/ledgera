@extends('layouts.app')

@section('titulo', 'LEDGERA')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}">
@endpush

@section('contenido')
    <!-- TÍTULO PRINCIPAL LEDEGERA -->
    <div class="brand-header">
        <h1 class="brand-title">LEDGERA</h1>
    </div>

    <!-- Formulario de Login -->
    <div class="login-wrapper">
        <form class="form" id="loginForm">
            <div class="title">
                Bienvenido(a),<br><span>inicia sesión para continuar</span>
            </div>

            <input
                type="text"
                placeholder="Usuario"
                name="username"
                id="loginUsername"
                class="input"
                autocomplete="username"
                required
            />

            <input
                type="password"
                placeholder="Contraseña"
                name="password"
                id="loginPassword"
                class="input"
                autocomplete="current-password"
                required
            />

            <div class="message-container">
                <span class="error-message" id="loginError"></span>
                <span class="success-message" id="loginSuccess"></span>
            </div>

            <button type="submit" class="button-confirm">
                Ingresar →
            </button>

            <p class="admin-note">
                <i class="fas fa-lock"></i>
                ¿No tienes acceso? Contacta al adminsitrador.
            </p>
        </form>
    </div>

    
@endsection

@push('scripts')
    <script src="{{ asset('js/auth.js') }}?v={{ filemtime(public_path('js/auth.js')) }}"></script>
@endpush
