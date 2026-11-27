<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'LEDGERA')</title>
    @stack('estilos')
    <link rel="stylesheet" href="{{ asset('css/tema.css') }}?v={{ filemtime(public_path('css/tema.css')) }}">
</head>
<body class="@yield('tema')">
@yield('contenido')

@php
    $u = auth()->user();
    $datosUsuario = $u ? [
        'id' => $u->id_usuario,
        'nombre' => $u->nombre_completo,
        'rol' => $u->rol,
        // Solo para mostrar u ocultar botones: el servidor vuelve a validar
        'puede_productos' => $u->puede('gestionar_productos'),
    ] : null;
@endphp
<script>
    window.LEDGERA = {
        base: @json(url('/')),
        usuario: @json($datosUsuario)
    };
</script>
<script src="{{ asset('js/api.js') }}?v={{ filemtime(public_path('js/api.js')) }}"></script>
<script src="{{ asset('js/filtros.js') }}?v={{ filemtime(public_path('js/filtros.js')) }}"></script>
@stack('scripts')
</body>
</html>
