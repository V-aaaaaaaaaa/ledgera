@extends('layouts.app')

@section('titulo', 'LEDGERA - Configura tu puesto')
@section('tema', 'tema-operativo')

@push('estilos')
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/configuracion-inicial.css') }}?v={{ filemtime(public_path('css/configuracion-inicial.css')) }}">
@endpush

@section('contenido')
<main class="cfg">
    <header class="cfg__cab">
        <p class="cfg__marca">Led<span>gera</span></p>
        <h1>Configura tu puesto</h1>
        <p class="cfg__intro">
            Cuéntanos <strong>qué productos vendes</strong> y sus <strong>calidades</strong>
            (por ejemplo: Tomate → Chonto, Larga vida). Así podrás registrar ventas e inventario.
        </p>
    </header>

    {{-- Lo que el puesto ya tiene (si volvió a esta pantalla) --}}
    @if (count($existentes) > 0)
        <section class="cfg__ya">
            <h2>Ya registrados</h2>
            <ul>
                @foreach ($existentes as $p)
                    <li><strong>{{ $p['nombre'] }}</strong>
                        @foreach ($p['calidades'] as $c)<span class="cfg__chip cfg__chip--fija">{{ $c }}</span>@endforeach
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="cfg__lista" id="lista" aria-live="polite"></section>

    <button type="button" class="cfg__agregar" id="btnAgregar">
        <i class="fas fa-plus"></i> Agregar otro producto
    </button>

    <p class="cfg__ayuda">
        <i class="fas fa-circle-info"></i>
        Escribe cada calidad y presiona <strong>Enter</strong> o <strong>+</strong>.
        Si un producto no maneja calidades, déjalo vacío y se guarda como <em>Única</em>.
        Podrás agregar más después.
    </p>

    <p class="cfg__msg" id="msg" role="alert" hidden></p>

    <div class="cfg__botones">
        <button type="button" class="cfg__btn cfg__btn--sec" id="btnOmitir">Lo hago después</button>
        <button type="button" class="cfg__btn" id="btnGuardar">Guardar y empezar</button>
    </div>
</main>

{{-- Plantilla de un producto (la copia el JS) --}}
<template id="tplProducto">
    <article class="cfg__producto">
        <div class="cfg__fila">
            <label class="cfg__etiqueta">Producto
                <input type="text" class="cfg__nombre" data-filtro="texto" maxlength="100" placeholder="ej: Tomate">
            </label>
            <button type="button" class="cfg__quitar" title="Quitar producto" aria-label="Quitar producto">
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <span class="cfg__etiqueta">Calidades</span>
        <div class="cfg__chips"></div>
        <div class="cfg__nueva">
            <input type="text" class="cfg__calidad" data-filtro="texto" maxlength="100" placeholder="ej: Chonto">
            <button type="button" class="cfg__mas" aria-label="Agregar calidad"><i class="fas fa-plus"></i></button>
        </div>
    </article>
</template>
@endsection

@push('scripts')
    <script src="{{ asset('js/configuracion-inicial.js') }}?v={{ filemtime(public_path('js/configuracion-inicial.js')) }}"></script>
@endpush
