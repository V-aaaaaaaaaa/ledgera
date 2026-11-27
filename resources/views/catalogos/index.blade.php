
@extends('layouts.operativo')

@section('titulo', 'LEDGERA - ' . $cfg['titulo'])
@section('tema', 'tema-inventario')


@push('estilos')

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet"
          href="{{ asset('css/catalogos.css') }}?v={{ filemtime(public_path('css/catalogos.css')) }}">

@endpush


@section('operativo-contenido')


<header class="cat-header">

    <a href="{{ route('dashboard') }}"
       class="cat-back"
       aria-label="Volver al dashboard">

        <i class="fas fa-arrow-left"></i>
        Volver

    </a>

    <strong>Led<span class="logo-acento">gera</span></strong>

    <div class="cat-user">

        <span>{{ auth()->user()->nombre_completo }}</span>

        <i class="fas fa-user-circle"></i>

    </div>

</header>


<main class="cat-main">

    <div class="cat-heading">
        <div>
            <small>Gestión comercial</small>
            <h1>{{ $cfg['titulo'] }}</h1>
            <p>Consulta y administra los registros de tu puesto.</p>
        </div>

        <button class="cat-primary"
                onclick="document.getElementById('crear').showModal()">
            + Registrar
        </button>
    </div>



    <div class="cat-stats">

        <article>
            <span>Registrados</span>
            <strong>{{ $total }}</strong>
        </article>

        <article>
            <span>Activos</span>
            <strong>{{ $activos }}</strong>
        </article>

        <article>
            @if($tipo === 'productos')
                <span>Inactivos</span>
                <strong>{{ $total - $activos }}</strong>
            @else
                <span>Saldo pendiente</span>
                <strong>
                    ${{ number_format($saldoTotal, 0, ',', '.') }}
                </strong>
            @endif
        </article>

    </div>

    @if(session('success'))
        <div class="cat-message success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="cat-message error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="GET" class="cat-filters">
        <input type="search"
               name="buscar"
               value="{{ request('buscar') }}"
               placeholder="Buscar registro...">

        <select name="estado">
            <option value="">Todos los estados</option>
            <option value="Activo"
                @selected(request('estado') === 'Activo')>
                Activos
            </option>
            <option value="Inactivo"
                @selected(request('estado') === 'Inactivo')>
                Inactivos
            </option>
        </select>

        <button class="cat-primary">Filtrar</button>

        <a class="cat-secondary"
           href="{{ route('catalogos.index', $tipo) }}">
            Limpiar
        </a>
    </form>

    <div class="cat-table-wrapper">
        <table class="cat-table">
            <thead>
                <tr>
                    <th>Nombre</th>

                    @if($tipo !== 'productos')
                        <th>Teléfono</th>
                    @else
                        <th>Descripción</th>
                    @endif

                    @if($tipo === 'clientes')
                        <th>Plazo</th>
                    @endif

                    <th>Estado</th>

                    @if($tipo !== 'productos')
                        <th>Saldo pendiente</th>
                    @endif

                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @forelse($registros as $r)
                    <tr>
                        <td>{{ $r->{$cfg['nombre']} }}</td>

                        @if($tipo !== 'productos')
                            <td>{{ $r->telefono ?? '—' }}</td>
                        @else
                            <td>{{ $r->descripcion ?? '—' }}</td>
                        @endif

                        @if($tipo === 'clientes')
                            <td>{{ $r->plazo_credito_dias }} días</td>
                        @endif

                        <td>
                            <span class="cat-badge
                                {{ $r->estado === 'Activo'
                                    ? 'active' : 'inactive' }}">
                                {{ $r->estado }}
                            </span>
                        </td>

                        @if($tipo !== 'productos')
                            <td class="cat-money">
                                ${{ number_format($r->saldo, 0, ',', '.') }}
                            </td>
                        @endif

                        <td>
                            <div class="cat-actions">

                                @if($esDueno || $tipo === 'proveedores')
                                    <button type="button"
                                            class="cat-secondary"
                                            onclick="document.getElementById('editar-{{ $r->{$cfg['id']} }}').showModal()">
                                        Editar
                                    </button>
                                @endif

                                @if($esDueno)
                                    <form method="POST"
                                          action="{{ route('catalogos.estado', [$tipo, $r->{$cfg['id']}]) }}"
                                          onsubmit="return confirm('¿Confirmar cambio de estado?')">
                                        @csrf

                                        <input type="hidden"
                                               name="estado"
                                               value="{{ $r->estado === 'Activo' ? 'Inactivo' : 'Activo' }}">

                                        <button class="cat-secondary">
                                            {{ $r->estado === 'Activo'
                                                ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                @endif

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="cat-empty">
                            No hay registros con estos filtros.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="cat-pagination">
        @if($registros->previousPageUrl())
            <a class="cat-secondary"
               href="{{ $registros->previousPageUrl() }}">
                Anterior
            </a>
        @endif

        Página {{ $registros->currentPage() }}
        de {{ $registros->lastPage() }}

        @if($registros->nextPageUrl())
            <a class="cat-secondary"
               href="{{ $registros->nextPageUrl() }}">
                Siguiente
            </a>
        @endif
    </div>

</main>

{{-- Modal de registro --}}

@if($tipo === 'productos')
    @include('catalogos.registro-producto')
@else

<dialog id="crear" class="cat-modal">

    <form method="POST"
          action="{{ route('catalogos.store', $tipo) }}">

        @csrf

        <h2>Registrar {{ strtolower($cfg['singular']) }}</h2>

        <label>Nombre *</label>
        <input name="nombre"
               value="{{ old('nombre') }}"
               maxlength="{{ $tipo === 'productos' ? 100 : 150 }}"
               required>

        @if($tipo !== 'productos')
            <label>Teléfono {{ $tipo === 'clientes' ? '*' : '' }}</label>
            <input name="telefono"
                   type="tel"
                   value="{{ old('telefono') }}"
                   maxlength="20"
                   @required($tipo === 'clientes')>
        @else
            <label>Descripción</label>
            <textarea name="descripcion"
                      rows="3">{{ old('descripcion') }}</textarea>
        @endif

        @if($tipo === 'clientes')
            <label>Plazo de crédito en días</label>

            <input type="number"
                   name="plazo"
                   min="1"
                   value="{{ old('plazo', 15) }}"
                   @disabled(!$esDueno)
                   required>
        @endif

        @if($tipo !== 'productos')
            <label class="cat-consent">
                <input type="checkbox"
                       name="consentimiento"
                       value="1"
                       required>

                Confirmo que cuento con autorización para
                registrar y tratar estos datos de contacto.
            </label>
        @endif

        <div class="cat-modal-actions">
            <button type="button"
                    class="cat-secondary"
                    onclick="this.closest('dialog').close()">
                Cancelar
            </button>

            <button class="cat-primary">Guardar registro</button>
        </div>

    </form>
</dialog>

@endif


{{-- Modales de edición --}}

@foreach($registros as $r)

    @if($esDueno || $tipo === 'proveedores')

        <dialog id="editar-{{ $r->{$cfg['id']} }}"
                class="cat-modal">

            <form method="POST"
                  action="{{ route('catalogos.update', [$tipo, $r->{$cfg['id']}]) }}">

                @csrf
                @method('PUT')

                <h2>Editar {{ strtolower($cfg['singular']) }}</h2>

                <label>Nombre *</label>
                <input name="nombre"
                       value="{{ $r->{$cfg['nombre']} }}"
                       required>

                @if($tipo !== 'productos')
                    <label>Teléfono</label>
                    <input name="telefono"
                           type="tel"
                           value="{{ $r->telefono }}"
                           @required($tipo === 'clientes')>
                @else
                    <label>Descripción</label>
                    <textarea name="descripcion"
                              rows="3">{{ $r->descripcion }}</textarea>
                @endif

                @if($tipo === 'clientes')
                    <label>Plazo de crédito en días</label>
                    <input type="number"
                           name="plazo"
                           min="1"
                           value="{{ $r->plazo_credito_dias }}"
                           required>
                @endif

                <div class="cat-modal-actions">
                    <button type="button"
                            class="cat-secondary"
                            onclick="this.closest('dialog').close()">
                        Cancelar
                    </button>

                    <button class="cat-primary">
                        Guardar cambios
                    </button>
                </div>

            </form>
        </dialog>

    @endif

@endforeach

@endsection
