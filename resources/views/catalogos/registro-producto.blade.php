@php
    $valor = static fn ($v) => is_scalar($v) ? $v : '';
    $unidades = \App\Services\InventarioService::UNIDADES;
    $acordadoAnterior = $valor(old('valor_acordado'));
@endphp

@push('estilos')
    <link rel="stylesheet"
          href="{{ asset('css/registro-producto.css') }}?v={{ filemtime(public_path('css/registro-producto.css')) }}">
@endpush

<dialog id="crear"
        class="cat-modal rp-modal"
        aria-labelledby="rp-titulo"
        data-reabrir="{{ $errors->producto->any() ? '1' : '0' }}"
        data-productos-previos="{{ json_encode(old('productos', []), JSON_INVALID_UTF8_SUBSTITUTE) }}">

    <form method="POST"
          action="{{ route('entradas.store') }}"
          id="rp-form">

        @csrf

        <header class="rp-header">
            <div class="rp-icon">
                <i class="fas fa-truck-loading"></i>
            </div>

            <div class="rp-heading">
                <h2 id="rp-titulo">Entrada de mercancía</h2>
                <p>Registra compras y actualiza tu inventario.</p>
            </div>

            <button type="button"
                    class="rp-close"
                    data-rp-close
                    aria-label="Cerrar formulario">
                &times;
            </button>
        </header>

        @if($errors->producto->any())
            <div class="cat-message error" role="alert">
                @foreach($errors->producto->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- 01. INFORMACIÓN DE LA COMPRA --}}

        <section class="rp-section">

            <h3><span>01</span> Información de la compra</h3>

            <div class="rp-provider-row">

                <label>
                    Proveedor *

                    <select name="id_proveedor"
                            id="rp-proveedor"
                            required>

                        <option value="">Seleccionar proveedor</option>

                        @foreach($proveedores as $proveedor)
                            <option value="{{ $proveedor->id_proveedor }}"
                                @selected(
                                    (string) $valor(old('id_proveedor'))
                                    === (string) $proveedor->id_proveedor
                                )>
                                {{ $proveedor->nombre_razon_social }}
                            </option>
                        @endforeach

                    </select>
                </label>

                @if($esDueno)
                    <button type="button"
                            class="cat-secondary"
                            id="rp-nuevo-proveedor">
                        <i class="fas fa-plus"></i>
                        Nuevo proveedor
                    </button>
                @endif

            </div>

            <p class="rp-note"
               id="rp-sugerencia-proveedor"
               hidden></p>

            <p class="rp-note"
               id="rp-sin-proveedores"
               @if($proveedores->isNotEmpty()) hidden @endif>

                @if($esDueno)
                    Registra un proveedor para continuar.
                @else
                    Solicita al dueño registrar el proveedor.
                @endif

            </p>

            <div class="rp-grid">

                <label>
                    Fecha de ingreso *

                    <input type="date"
                           name="fecha_entrada"
                           max="{{ now()->toDateString() }}"
                           value="{{ $valor(old('fecha_entrada', now()->toDateString())) }}"
                           required>
                </label>

                <div class="rp-documento">

                    <label>
                        Factura o referencia interna *

                        <input id="rp-documento"
                               name="documento_soporte"
                               maxlength="50"
                               value="{{ $valor(old('documento_soporte')) }}"
                               placeholder="Número de factura"
                               required>
                    </label>

                    <button type="button"
                            class="cat-secondary"
                            id="rp-generar-documento"
                            data-referencia="INT-{{ random_int(10000000, 99999999) }}">

                        <i class="fas fa-file-alt"></i>
                        Generar referencia interna
                    </button>

                    <p class="rp-note">
                        Para compras sin factura o remisión.
                    </p>

                </div>

            </div>

        </section>

        {{-- 02. PRODUCTOS RECIBIDOS --}}

        <section class="rp-section">

            <h3><span>02</span> Productos recibidos</h3>

            <p class="rp-help">
                Agrega los productos y sus respectivas presentaciones.
            </p>

            <p id="rp-estado-carga"
               class="rp-note"
               role="status">
                Cargando catálogo...
            </p>

            <div id="rp-productos"></div>

            <button type="button"
                    class="cat-secondary"
                    id="rp-agregar-producto"
                    disabled>

                <i class="fas fa-plus"></i>
                Agregar producto
            </button>

        </section>

        {{-- PLANTILLA DE PRODUCTO --}}

        <template id="rp-template-producto">

            <article class="rp-product-card">

                <header class="rp-product-header">

                    <h4 data-titulo-producto>Producto</h4>

                    <button type="button"
                            class="rp-remove"
                            data-eliminar-producto
                            aria-label="Eliminar producto">

                        <i class="fas fa-trash"></i>
                    </button>

                </header>

                <label>
                    Seleccionar producto *

                    <select data-selector-producto required>

                        <option value="">
                            Seleccionar del catálogo
                        </option>

                        <option value="nuevo">
                            + Registrar nuevo producto
                        </option>

                    </select>
                </label>

                <input type="hidden"
                       data-producto-campo="id_producto">

                <label data-nombre-nuevo hidden>

                    Nombre del nuevo producto *

                    <input data-producto-campo="nombre"
                           maxlength="100"
                           minlength="2"
                           placeholder="Ej. Zanahoria"
                           disabled>
                </label>

                <div class="rp-types-header">

                    <h4>Tipos y presentaciones</h4>

                    <p class="rp-help">
                        Selecciona un tipo y su unidad comercial.
                    </p>

                </div>

                <div data-contenedor-tipos></div>

                <button type="button"
                        class="cat-secondary"
                        data-agregar-tipo>

                    <i class="fas fa-plus"></i>
                    Agregar presentación
                </button>

                <div class="rp-product-total">
                    Subtotal del producto:

                    <strong data-total-producto>$0</strong>
                </div>

            </article>

        </template>

        {{-- PLANTILLA DE PRESENTACIÓN --}}

        <template id="rp-template-tipo">

            <div class="rp-quality">

                <label>
                    Tipo *

                    <select data-selector-tipo required>

                        <option value="">
                            Seleccionar tipo
                        </option>

                        <option value="__nuevo__">
                            + Registrar nuevo tipo
                        </option>

                    </select>

                    <input type="text"
                           data-nombre-tipo-nuevo
                           maxlength="100"
                           placeholder="Nombre del nuevo tipo"
                           aria-label="Nombre del nuevo tipo"
                           hidden
                           disabled>

                    <input type="hidden"
                           data-campo="nombre">
                </label>

                <label>
                    Unidad comercial *

                    <select data-campo="unidad_comercial" required>

                        <option value="">
                            Seleccionar unidad
                        </option>

                        @foreach($unidades as $unidad)
                            <option value="{{ $unidad }}">
                                {{ $unidad }}
                            </option>
                        @endforeach

                    </select>

                    <small data-stock-tipo
                           class="rp-note"></small>
                </label>

                <label>
                    Cantidad recibida *

                    <input data-campo="cantidad"
                           type="number"
                           min="1"
                           max="1000000"
                           step="1"
                           placeholder="0"
                           required>
                </label>

                <label>
                    Costo unitario *

                    <input data-campo="costo"
                           type="number"
                           min="1"
                           max="9999999999"
                           step="1"
                           placeholder="COP"
                           required>
                </label>

                <label>
                    Precio de venta unitario

                    <input data-campo="precio"
                           type="number"
                           min="1"
                           max="9999999999"
                           step="1"
                           placeholder="COP">

                    <small data-info-precio
                           class="rp-note"></small>
                </label>

                <button type="button"
                        class="rp-remove"
                        data-eliminar-tipo
                        aria-label="Eliminar presentación">

                    <i class="fas fa-trash"></i>
                </button>

                <div class="rp-subtotal">
                    Subtotal de compra:

                    <strong data-subtotal>$0</strong>
                </div>

            </div>

        </template>

        {{-- 03. RESUMEN FINANCIERO --}}

        <section class="rp-section">

            <h3><span>03</span> Resumen financiero</h3>

            <div class="rp-summary" aria-live="polite">

                <div>
                    <small>Productos en la compra</small>
                    <strong id="rp-total-productos">0</strong>
                    <span id="rp-total-tipos">0 presentaciones</span>
                </div>

                <div>
                    <small>Costo total calculado</small>
                    <strong id="rp-total">$0</strong>
                    <span>COP</span>
                </div>

            </div>

            <div class="rp-negociacion">

                <h4>Valor negociado con el proveedor</h4>

                <p class="rp-help">
                    Registra un valor diferente si hubo una negociación.
                </p>

                <label class="rp-negociacion-control">

                    <input type="checkbox"
                           id="rp-usar-valor-acordado"
                           @checked($acordadoAnterior !== '')>

                    Registrar un valor negociado diferente
                </label>

                <div id="rp-campo-valor-acordado"
                     @if($acordadoAnterior === '') hidden @endif>

                    <label>
                        Valor acordado *

                        <input type="number"
                               name="valor_acordado"
                               id="rp-valor-acordado"
                               min="1"
                               max="9999999999"
                               step="1"
                               value="{{ $acordadoAnterior }}"
                               placeholder="COP"
                               @disabled($acordadoAnterior === '')>
                    </label>

                </div>

            </div>

            <div class="rp-financial-summary"
                 aria-live="polite">

                <div class="rp-financial-row">
                    <span>Valor calculado</span>
                    <strong id="rp-valor-calculado">$0</strong>
                </div>

                <div class="rp-financial-row">
                    <span id="rp-diferencia-etiqueta">
                        Diferencia negociada
                    </span>
                    <strong id="rp-diferencia">$0</strong>
                </div>

                <div class="rp-financial-row rp-financial-total">
                    <span>Valor definitivo de la compra</span>
                    <strong id="rp-total-acordado">$0</strong>
                </div>

                <div class="rp-financial-row">
                    <span>Pago registrado</span>
                    <strong>$0</strong>
                </div>

                <div class="rp-financial-row rp-financial-pending">
                    <span>Saldo pendiente con el proveedor</span>
                    <strong id="rp-saldo-pendiente">$0</strong>
                </div>

            </div>

            <p class="rp-note">

                <i class="fas fa-info-circle"></i>

                La compra quedará pendiente de pago.

                @if($esDueno)
                    Puedes registrar el pago desde Egresos.
                @endif

            </p>

            <p id="rp-error-total"
               class="cat-message error"
               role="alert"
               hidden></p>

        </section>

        <div class="cat-modal-actions rp-footer">

            <button type="button"
                    class="cat-secondary"
                    data-rp-close>
                Cancelar
            </button>

            <button type="submit"
                    class="cat-primary"
                    id="rp-guardar"
                    disabled>

                <i class="fas fa-check"></i>
                Registrar entrada de mercancía
            </button>

        </div>

    </form>

</dialog>

{{-- REGISTRO DE PROVEEDORES: EXCLUSIVO DEL DUEÑO --}}

@if($esDueno)

    <dialog id="rp-modal-proveedor"
            class="cat-modal rp-provider-modal"
            aria-labelledby="rp-titulo-proveedor">

        <form id="rp-form-proveedor"
              method="POST"
              action="{{ route('proveedores.store') }}">

            @csrf

            <header class="rp-provider-header">

                <i class="fas fa-truck"></i>

                <div>
                    <h2 id="rp-titulo-proveedor">Nuevo proveedor</h2>
                    <p>Agrega un proveedor a tu negocio.</p>
                </div>

            </header>

            <label>
                Nombre o razón social *

                <input name="nombre"
                       maxlength="150"
                       required>
            </label>

            <label>
                Teléfono

                <input name="telefono"
                       type="tel"
                       maxlength="10"
                       pattern="[0-9]{7,10}">
            </label>

            <label>
                Contacto principal

                <input name="contacto"
                       maxlength="100">
            </label>

            <label class="cat-consent">

                <input type="checkbox"
                       name="consentimiento"
                       value="1"
                       required>

                Confirmo que el proveedor autorizó el
                tratamiento de sus datos.
            </label>

            <div id="rp-error-proveedor"
                 class="cat-message error"
                 role="alert"
                 hidden></div>

            <div class="cat-modal-actions">

                <button type="button"
                        class="cat-secondary"
                        id="rp-cancelar-proveedor">
                    Cancelar
                </button>

                <button type="submit"
                        class="cat-primary"
                        id="rp-guardar-proveedor">
                    Guardar proveedor
                </button>

            </div>

        </form>

    </dialog>

@endif

@push('scripts')

    <script src="{{ asset('js/registro-producto.js') }}?v={{ filemtime(public_path('js/registro-producto.js')) }}"></script>

@endpush