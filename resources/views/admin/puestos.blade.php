@extends('layouts.app')

@section('titulo', 'LEDEGERA - Puestos')
@section('tema', 'tema-puestos')

@push('estilos')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/puestos.css') }}?v={{ filemtime(public_path('css/puestos.css')) }}">
@endpush

@section('contenido')
    <!-- HEADER -->
    <header class="app-header">
        <button class="btn-back" id="btnBack" title="Volver al dashboard">
            <i class="fas fa-arrow-left"></i>
        </button>

        <h1 class="header-title">Puestos</h1>

        <div class="header-user">
            <span id="headerUserName">Admin</span>
            <i class="fas fa-user-circle"></i>
        </div>
    </header>

    <!-- CONTENIDO -->
    <main class="app-main">

        <div class="section-bar">
            <h2 class="section-title">Listado de puestos</h2>
            <button class="btn-create" id="btnCreate">
                <i class="fas fa-plus"></i> Crear
            </button>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Bodega</th>
                        <th>Dueño</th>
                        <th>Estado</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody id="puestosTableBody">
                    <!-- Filas generadas por JS -->
                </tbody>
            </table>

            <div class="empty-state" id="emptyState" style="display:none;">
                <i class="fas fa-store"></i>
                <p>No hay puestos registrados todavía.</p>
            </div>
        </div>

    </main>

    <!-- ==================== MODAL: CREAR PUESTO ==================== -->
    <div class="modal-overlay" id="createModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Registrar nuevo puesto</h3>
                <button class="modal-close" data-close="createModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form class="modal-form" id="createPuestoForm">
                <div class="form-row">
                    <label for="newNombre">Nombre del puesto *</label>
                    <input type="text" id="newNombre" placeholder="ej: Puesto Central" data-filtro="texto" maxlength="100" required />
                </div>

                <div class="form-row">
                    <label for="newBodega">Bodega *</label>
                    <input type="text" id="newBodega" placeholder="ej: Bodega Norte" data-filtro="texto" maxlength="100" required />
                </div>

                <div class="message-container">
                    <span class="error-message" id="createError"></span>
                    <span class="success-message" id="createSuccess"></span>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-close="createModal">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar puesto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: EDITAR PUESTO ==================== -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Editar puesto</h3>
                <button class="modal-close" data-close="editModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form class="modal-form" id="editPuestoForm">
                <input type="hidden" id="editId" />

                <div class="form-row">
                    <label for="editNombre">Nombre del puesto *</label>
                    <input type="text" id="editNombre" data-filtro="texto" maxlength="100" required />
                </div>

                <div class="form-row">
                    <label for="editBodega">Bodega *</label>
                    <input type="text" id="editBodega" data-filtro="texto" maxlength="100" required />
                </div>

                <div class="message-container">
                    <span class="error-message" id="editError"></span>
                    <span class="success-message" id="editSuccess"></span>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-close="editModal">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== MODAL: CAMBIAR DUEÑO ==================== -->
    <div class="modal-overlay" id="ownerModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Cambiar dueño del puesto</h3>
                <button class="modal-close" data-close="ownerModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form class="modal-form" id="ownerForm">
                <input type="hidden" id="ownerPuestoId" />

                <div class="form-row">
                    <label>Puesto</label>
                    <input type="text" id="ownerPuestoNombre" readonly />
                </div>

                <div class="form-row">
                    <label for="ownerSelect">Nuevo dueño *</label>
                    <select id="ownerSelect" class="select" required>
                        <option value="">-- Selecciona un dueño --</option>
                    </select>
                    <small class="hint">
                        Selecciona un dueño inactivo registrado en este
                        puesto. El dueño anterior perderá el acceso,
                        pero conservará su historial.
                    </small>
                </div>

                <div class="message-container">
                    <span class="error-message" id="ownerError"></span>
                    <span class="success-message" id="ownerSuccess"></span>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-close="ownerModal">Cancelar</button>
                    <button type="submit" class="btn-primary">Asignar dueño</button>
                </div>
            </form>
        </div>
    </div>

    
@endsection

@push('scripts')
    <script src="{{ asset('js/puestos.js') }}?v={{ filemtime(public_path('js/puestos.js')) }}"></script>
@endpush
