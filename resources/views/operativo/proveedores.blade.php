@extends('layouts.operativo')

@section('titulo', 'LEDGERA - Proveedores')
@section('tema', 'tema-proveedores')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    {{-- Reutiliza los estilos de tabla y ventanas de Usuarios; proveedores.css solo agrega lo propio --}}
    <link rel="stylesheet" href="{{ asset('css/usuarios.css') }}?v={{ filemtime(public_path('css/usuarios.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/proveedores.css') }}?v={{ filemtime(public_path('css/proveedores.css')) }}">
@endpush

@section('operativo-contenido')
    <header class="app-header">
        <button class="btn-back" id="btnBack" title="Volver al inicio">
            <i class="fas fa-arrow-left"></i>
        </button>

        <h1 class="header-title">Proveedores</h1>

        <div class="header-user">
            <span id="headerUserName"></span>
            <i class="fas fa-user-circle"></i>
        </div>
    </header>

    <main class="app-main">
        <div class="section-bar">
            <h2 class="section-title">Mis proveedores</h2>
            <button class="btn-create" id="btnCreate">
                <i class="fas fa-plus"></i> Nuevo
            </button>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Proveedor</th>
                        <th>Teléfono</th>
                        <th>Contacto</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody id="tablaProveedores"></tbody>
            </table>

            <div class="empty-state" id="emptyState" style="display:none;">
                <i class="fas fa-truck"></i>
                <p>Todavía no tienes proveedores registrados.</p>
            </div>
        </div>
    </main>

    {{-- Una sola ventana para crear y editar --}}
    <div class="modal-overlay" id="modalProveedor">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitulo">Nuevo proveedor</h3>
                <button class="modal-close" data-close="modalProveedor">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form class="modal-form" id="formProveedor" novalidate>
                <input type="hidden" id="provId" />

                <div class="form-row">
                    <label for="provNombre">Nombre o razón social *</label>
                    <input type="text" id="provNombre" data-filtro="texto" maxlength="150" placeholder="ej: Don Pedro - Finca El Salitre" required />
                </div>

                <div class="form-row">
                    <label for="provTelefono">Teléfono</label>
                    <input type="tel" id="provTelefono" data-filtro="numeros" inputmode="numeric" maxlength="10" placeholder="ej: 3109876543" />
                </div>

                <div class="form-row">
                    <label for="provContacto">Persona de contacto</label>
                    <input type="text" id="provContacto" data-filtro="letras" maxlength="100" placeholder="ej: Pedro Gómez" />
                </div>

                {{-- Ley 1581 (Habeas Data): autorización obligatoria --}}
                <label class="check-consentimiento" id="filaConsentimiento">
                    <input type="checkbox" id="provConsentimiento" />
                    <span>El proveedor autorizó el tratamiento de sus datos personales (Ley 1581 de 2012).</span>
                </label>

                <div class="message-container">
                    <span class="error-message" id="provError"></span>
                    <span class="success-message" id="provExito"></span>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-close="modalProveedor">Cancelar</button>
                    <button type="submit" class="btn-primary" id="btnGuardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/proveedores.js') }}?v={{ filemtime(public_path('js/proveedores.js')) }}"></script>
@endpush
