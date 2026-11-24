@extends('layouts.app')

@section('titulo', 'LEDGERA')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}">
@endpush

@section('contenido')
    <div class="brand-header">
        <h1 class="brand-title">LEDGERA</h1>
    </div>

    <div class="login-wrapper">
        <form class="form" id="loginForm" method="POST" action="{{ url('/login') }}">
            @csrf

            <div class="title">
                Bienvenido(a),<br><span>inicia sesión para continuar</span>
            </div>

            <input
                type="text"
                placeholder="Usuario"
                name="usuario"
                id="loginUsername"
                class="input"
                value="{{ old('usuario') }}"
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
                @if ($errors->any())
                    <span class="error-message" style="display:block">{{ $errors->first() }}</span>
                @endif
            </div>

            <button type="submit" class="button-confirm">
                Ingresar →
            </button>

            <p class="admin-note">
                <i class="fas fa-lock"></i>
                ¿No tienes acceso? Contacta al administrador.
            </p>
        </form>
    </div>
@endsection