'use strict';

document.addEventListener('DOMContentLoaded', () => {

    const modal = document.querySelector('.rp-modal');

    if (!modal) return;

    const $ = selector => modal.querySelector(selector);

    const form = $('#rp-form');
    const lista = $('#rp-productos');
    const plantillaProducto = $('#rp-template-producto');
    const plantillaTipo = $('#rp-template-tipo');

    const agregarProducto = $('#rp-agregar-producto');
    const guardar = $('#rp-guardar');
    const proveedor = $('#rp-proveedor');
    const documento = $('#rp-documento');
    const estadoCarga = $('#rp-estado-carga');

    const usarAcordado = $('#rp-usar-valor-acordado');
    const valorAcordado = $('#rp-valor-acordado');

    const MAX_PRODUCTOS = 20;
    const MAX_TIPOS = 20;
    const MAX_LINEAS = 150;
    const LIMITE_TOTAL = 9999999999n;
    const NUEVO_TIPO = '__nuevo__';

    const moneda = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });

    const numero = new Intl.NumberFormat('es-CO');

    let catalogo = [];
    let cargado = false;
    let enviando = false;

    // =====================================================
    // FUNCIONES AUXILIARES
    // =====================================================

    const texto = valor =>
        typeof valor === 'string' || typeof valor === 'number'
            ? String(valor)
            : '';

    const limpiar = valor =>
        texto(valor).trim().replace(/\s+/g, ' ');

    const normalizar = valor =>
        limpiar(valor)
            .toLocaleLowerCase('es-CO')
            .normalize('NFD')
            .replace(/\p{M}/gu, '');

    const entero = input =>
        /^\d+$/.test(input.value)
            ? BigInt(input.value)
            : 0n;

    const comoLista = valor =>
        valor && typeof valor === 'object'
            ? Object.values(valor).filter(
                item => item && typeof item === 'object'
            )
            : [];

    const tarjetas = () =>
        Array.from(lista.querySelectorAll('.rp-product-card'));

    const filas = tarjeta =>
        Array.from(tarjeta.querySelectorAll('.rp-quality'));

    const totalLineas = () =>
        lista.querySelectorAll('.rp-quality').length;

    const campoProducto = (tarjeta, nombre) =>
        tarjeta.querySelector(
            `[data-producto-campo="${nombre}"]`
        );

    const campoTipo = (fila, nombre) =>
        fila.querySelector(`[data-campo="${nombre}"]`);

    const selectorProducto = tarjeta =>
        tarjeta.querySelector('[data-selector-producto]');

    const selectorTipo = fila =>
        fila.querySelector('[data-selector-tipo]');

    const nuevoTipo = fila =>
        fila.querySelector('[data-nombre-tipo-nuevo]');

    function buscarProducto(id) {
        return catalogo.find(
            p => String(p.id_producto) === String(id)
        );
    }

    function productoSeleccionado(tarjeta) {
        return buscarProducto(
            selectorProducto(tarjeta).value
        );
    }

    function buscarTipo(producto, nombre) {
        return producto?.tipos?.find(
            t => normalizar(t.nombre) === normalizar(nombre)
        );
    }

    function buscarPresentacion(tipo, unidad) {
        return tipo?.presentaciones?.find(
            p => p.unidad_comercial === unidad
        );
    }

    function validarEntero(input) {
        input.setCustomValidity(
            input.value !== '' && !/^\d+$/.test(input.value)
                ? 'Ingresa un número entero válido.'
                : ''
        );
    }

    // =====================================================
    // CARGAR CATÁLOGO
    // =====================================================

    async function cargarCatalogo() {

        try {

            const respuesta = await api(
                '/entradas-mercancia/datos'
            );

            if (
                !respuesta?.ok ||
                !Array.isArray(respuesta.productos)
            ) {
                throw new Error('Catálogo no disponible.');
            }

            catalogo = respuesta.productos;
            cargado = true;

            estadoCarga.hidden = true;

            restaurarFormulario();
            actualizar();

        } catch (error) {

            cargado = false;

            if (error.message === 'sin sesion') return;

            estadoCarga.hidden = false;
            estadoCarga.textContent =
                'No se pudo cargar el catálogo. Recarga la página.';

            actualizar();
        }
    }

    // =====================================================
    // CREAR PRODUCTO
    // =====================================================

    function crearProducto(datos = {}) {

        const tarjeta = plantillaProducto.content
            .firstElementChild.cloneNode(true);

        const selector = selectorProducto(tarjeta);

        const opcionInicial = selector.querySelector(
            'option[value=""]'
        );

        opcionInicial.disabled = true;
        opcionInicial.hidden = true;

        catalogo.forEach(producto => {
            selector.add(new Option(
                producto.nombre_producto,
                producto.id_producto
            ));
        });

        campoProducto(tarjeta, 'nombre').value =
            texto(datos.nombre);

        if (datos.id_producto) {

            const id = String(datos.id_producto);

            if (!buscarProducto(id)) {
                selector.add(
                    new Option('Producto no disponible', id)
                );
            }

            selector.value = id;

        } else if (datos.nombre) {

            selector.value = 'nuevo';

        } else {

            selector.value = '';
        }

        selector.dataset.anterior = selector.value;

        lista.appendChild(tarjeta);

        configurarProducto(tarjeta);

        const anteriores = comoLista(datos.calidades);

        if (anteriores.length) {

            anteriores.forEach(tipo =>
                crearPresentacion(tarjeta, tipo)
            );

        } else {

            crearPresentacion(tarjeta);
        }

        // Eliminar cualquier producto, incluso el último.

        const eliminar = tarjeta.querySelector(
            '[data-eliminar-producto]'
        );

        eliminar.disabled = false;

        eliminar.addEventListener('click', evento => {

            evento.preventDefault();
            evento.stopPropagation();

            if (enviando) return;

            tarjeta.remove();

            actualizar();
        });

        tarjeta.querySelector('[data-agregar-tipo]')
            .addEventListener('click', () => {

                if (
                    enviando ||
                    filas(tarjeta).length >= MAX_TIPOS ||
                    totalLineas() >= MAX_LINEAS
                ) return;

                const fila = crearPresentacion(tarjeta);

                actualizar();

                if (selectorTipo(fila).value === NUEVO_TIPO) {
                    nuevoTipo(fila).focus();
                } else {
                    selectorTipo(fila).focus();
                }
            });

        return tarjeta;
    }

    function configurarProducto(tarjeta) {

        const selector = selectorProducto(tarjeta);
        const producto = productoSeleccionado(tarjeta);

        const esNuevo = selector.value === 'nuevo';
        const nombre = campoProducto(tarjeta, 'nombre');

        tarjeta.querySelector('[data-nombre-nuevo]')
            .hidden = !esNuevo;

        nombre.disabled = !esNuevo;
        nombre.required = esNuevo;

        campoProducto(tarjeta, 'id_producto').value =
            producto ? producto.id_producto : '';
    }

    // =====================================================
    // CREAR PRESENTACIÓN
    // =====================================================

    function crearPresentacion(tarjeta, datos = {}) {

        const fila = plantillaTipo.content
            .firstElementChild.cloneNode(true);

        ['unidad_comercial', 'cantidad', 'costo', 'precio']
            .forEach(campo => {

                campoTipo(fila, campo).value =
                    texto(datos[campo]);
            });

        tarjeta.querySelector('[data-contenedor-tipos]')
            .appendChild(fila);

        configurarSelectorTipos(
            tarjeta,
            fila,
            texto(datos.nombre)
        );

        fila.querySelector('[data-eliminar-tipo]')
            .addEventListener('click', evento => {

                evento.preventDefault();
                evento.stopPropagation();

                if (
                    enviando ||
                    filas(tarjeta).length <= 1
                ) return;

                fila.remove();

                actualizar();
            });

        return fila;
    }

    // =====================================================
    // CONFIGURAR SELECTOR DE TIPOS
    // =====================================================

    function configurarSelectorTipos(
        tarjeta,
        fila,
        anterior = ''
    ) {

        const selector = selectorTipo(fila);
        const campoNuevo = nuevoTipo(fila);
        const producto = productoSeleccionado(tarjeta);

        selector.replaceChildren();

        // Texto inicial: no seleccionable.

        const placeholder = new Option(
            'Seleccionar tipo',
            '',
            true,
            true
        );

        placeholder.disabled = true;
        placeholder.hidden = true;

        selector.add(placeholder);

        producto?.tipos?.forEach(tipo => {

            selector.add(new Option(
                tipo.nombre,
                String(tipo.id)
            ));
        });

        // Crear tipos nuevos: solo Dueño o Vendedor encargado
        if (window.LEDGERA?.usuario?.puede_productos) {
            selector.add(new Option(
                '+ Registrar nuevo tipo',
                NUEVO_TIPO
            ));
        }

        campoNuevo.value = '';

        const existente = buscarTipo(producto, anterior);

        if (existente) {

            selector.value = String(existente.id);

        } else if (anterior) {

            selector.value = NUEVO_TIPO;
            campoNuevo.value = anterior;

        } else if (
            selectorProducto(tarjeta).value === 'nuevo' ||
            (producto && !producto.tipos?.length)
        ) {

            selector.value = NUEVO_TIPO;

        } else {

            selector.value = '';
        }

        selector.disabled =
            !producto &&
            selectorProducto(tarjeta).value !== 'nuevo';

        sincronizarTipo(tarjeta, fila);
    }

    function sincronizarTipo(tarjeta, fila) {

        const selector = selectorTipo(fila);
        const campoNuevo = nuevoTipo(fila);
        const campoNombre = campoTipo(fila, 'nombre');

        const producto = productoSeleccionado(tarjeta);

        const esNuevo =
            !selector.disabled &&
            selector.value === NUEVO_TIPO;

        campoNuevo.hidden = !esNuevo;
        campoNuevo.disabled = !esNuevo;
        campoNuevo.required = esNuevo;

        campoNuevo.setCustomValidity('');
        selector.setCustomValidity('');

        if (esNuevo) {

            campoNombre.value = limpiar(campoNuevo.value);

        } else {

            const tipo = producto?.tipos?.find(
                t => String(t.id) === selector.value
            );

            campoNombre.value = tipo?.nombre || '';
        }
    }

    // =====================================================
    // EXISTENCIAS Y PRECIO DE VENTA
    // =====================================================

    function actualizarPresentacion(tarjeta, fila) {

        sincronizarTipo(tarjeta, fila);

        const producto = productoSeleccionado(tarjeta);

        const nombre = campoTipo(fila, 'nombre').value;
        const unidad = campoTipo(fila, 'unidad_comercial').value;

        const precio = campoTipo(fila, 'precio');

        const stockInfo = fila.querySelector(
            '[data-stock-tipo]'
        );

        const precioInfo = fila.querySelector(
            '[data-info-precio]'
        );

        const tipo = buscarTipo(producto, nombre);
        const presentacion = buscarPresentacion(tipo, unidad);

        stockInfo.textContent = '';

        if (presentacion) {

            stockInfo.textContent =
                `Stock actual: ${numero.format(presentacion.stock)}`;

        } else if (nombre && unidad) {

            stockInfo.textContent =
                'Stock de esta presentación: 0';

            if (tipo?.presentaciones?.some(
                p => p.unidad_comercial === 'Por definir'
            )) {
                stockInfo.textContent +=
                    '. Existe stock antiguo pendiente de clasificar.';
            }
        }

        if (
            presentacion &&
            Number(presentacion.precio) > 0
        ) {

            precio.value = texto(presentacion.precio);

            precio.disabled = true;
            precio.required = false;
            precio.dataset.bloqueado = '1';

            precio.setCustomValidity('');

            precioInfo.textContent =
                `Precio vigente: ${moneda.format(
                    Number(presentacion.precio)
                )}`;

        } else {

            if (precio.dataset.bloqueado === '1') {
                precio.value = '';
            }

            precio.disabled = false;
            precio.required = true;
            precio.dataset.bloqueado = '0';

            precioInfo.textContent =
                'Indica el precio inicial.';

            validarEntero(precio);
        }
    }

    // =====================================================
    // REINDEXAR CAMPOS
    // =====================================================

    function reindexar() {

        tarjetas().forEach((tarjeta, i) => {

            tarjeta.querySelectorAll('[data-producto-campo]')
                .forEach(input => {

                    input.name =
                        `productos[${i}][${input.dataset.productoCampo}]`;
                });

            filas(tarjeta).forEach((fila, j) => {

                fila.querySelectorAll('[data-campo]')
                    .forEach(input => {

                        input.name =
                            `productos[${i}][calidades][${j}][${input.dataset.campo}]`;
                    });
            });

            const producto = productoSeleccionado(tarjeta);

            const nombre = producto?.nombre_producto ||
                limpiar(campoProducto(tarjeta, 'nombre').value);

            tarjeta.querySelector('[data-titulo-producto]')
                .textContent = nombre
                    ? `Producto ${i + 1} — ${nombre}`
                    : `Producto ${i + 1}`;
        });
    }

    // =====================================================
    // VALIDAR DUPLICADOS
    // =====================================================

    function validarDuplicados() {

        const productosUsados = new Set();

        tarjetas().forEach(tarjeta => {

            const selector = selectorProducto(tarjeta);
            const nombre = campoProducto(tarjeta, 'nombre');

            const producto = productoSeleccionado(tarjeta);
            const esNuevo = selector.value === 'nuevo';

            selector.setCustomValidity('');
            nombre.setCustomValidity('');

            let clave = '';

            if (esNuevo) {

                clave = normalizar(nombre.value);

                if (limpiar(nombre.value).length < 2) {

                    nombre.setCustomValidity(
                        'Escribe un nombre válido.'
                    );

                } else if (catalogo.some(
                    p => normalizar(p.nombre_producto) === clave
                )) {

                    nombre.setCustomValidity(
                        'El producto ya existe. Selecciónalo del catálogo.'
                    );
                }

            } else if (producto) {

                clave = normalizar(producto.nombre_producto);

            } else if (selector.value) {

                selector.setCustomValidity(
                    'El producto no está disponible.'
                );
            }

            if (clave) {

                if (productosUsados.has(clave)) {

                    (esNuevo ? nombre : selector)
                        .setCustomValidity(
                            'Agrupa las presentaciones del mismo producto.'
                        );
                }

                productosUsados.add(clave);
            }

            const presentacionesUsadas = new Set();

            filas(tarjeta).forEach(fila => {

                const selector = selectorTipo(fila);
                const campoNuevo = nuevoTipo(fila);
                const unidad = campoTipo(fila, 'unidad_comercial');
                const nombre = campoTipo(fila, 'nombre').value;

                unidad.setCustomValidity('');

                if (selector.value === NUEVO_TIPO) {

                    if (!limpiar(nombre)) {

                        campoNuevo.setCustomValidity(
                            'Escribe el nombre del nuevo tipo.'
                        );

                    } else if (buscarTipo(producto, nombre)) {

                        campoNuevo.setCustomValidity(
                            'Este tipo ya existe. Selecciónalo de la lista.'
                        );
                    }
                }

                if (!nombre || !unidad.value) return;

                const clave = JSON.stringify([
                    normalizar(nombre),
                    unidad.value
                ]);

                if (presentacionesUsadas.has(clave)) {

                    unidad.setCustomValidity(
                        'Esta combinación de tipo y unidad está repetida.'
                    );
                }

                presentacionesUsadas.add(clave);
            });
        });
    }

    // =====================================================
    // SUGERENCIA DE PROVEEDOR
    // =====================================================

    function actualizarProveedor() {

        const sugeridos = new Set();

        tarjetas().forEach(tarjeta => {

            const producto = productoSeleccionado(tarjeta);

            if (producto?.ultimo_proveedor) {
                sugeridos.add(
                    String(producto.ultimo_proveedor)
                );
            }
        });

        const opciones = Array.from(proveedor.options)
            .filter(o => o.value && !o.disabled);

        const disponibles = opciones.filter(
            o => sugeridos.has(o.value)
        );

        const aviso = $('#rp-sugerencia-proveedor');

        $('#rp-sin-proveedores').hidden =
            opciones.length > 0;

        aviso.hidden = true;
        aviso.textContent = '';

        if (disponibles.length === 1) {

            aviso.textContent =
                `Proveedor sugerido: ${disponibles[0].textContent.trim()}. ` +
                'Confirma el proveedor de esta compra.';

            aviso.hidden = false;

        } else if (disponibles.length > 1) {

            aviso.textContent =
                'Selecciona el proveedor correspondiente a esta compra.';

            aviso.hidden = false;
        }
    }

    // =====================================================
    // CÁLCULOS FINANCIEROS
    // =====================================================

    function calcularTotal() {

        let total = 0n;

        tarjetas().forEach(tarjeta => {

            let subtotalProducto = 0n;

            filas(tarjeta).forEach(fila => {

                const cantidad = campoTipo(fila, 'cantidad');
                const costo = campoTipo(fila, 'costo');

                validarEntero(cantidad);
                validarEntero(costo);

                const subtotal =
                    entero(cantidad) * entero(costo);

                fila.querySelector('[data-subtotal]')
                    .textContent = moneda.format(subtotal);

                subtotalProducto += subtotal;
            });

            tarjeta.querySelector('[data-total-producto]')
                .textContent = moneda.format(subtotalProducto);

            total += subtotalProducto;
        });

        return total;
    }

    function actualizarTotales() {

        const total = calcularTotal();

        const productos = tarjetas().length;
        const lineas = totalLineas();

        $('#rp-total-productos').textContent = productos;

        $('#rp-total-tipos').textContent =
            `${lineas} ${lineas === 1
                ? 'presentación'
                : 'presentaciones'}`;

        $('#rp-total').textContent = moneda.format(total);

        $('#rp-campo-valor-acordado').hidden =
            !usarAcordado.checked;

        valorAcordado.disabled = !usarAcordado.checked;
        valorAcordado.required = usarAcordado.checked;

        validarEntero(valorAcordado);

        const acordado = usarAcordado.checked
            ? entero(valorAcordado)
            : total;

        const diferencia = acordado - total;

        $('#rp-valor-calculado').textContent =
            moneda.format(total);

        $('#rp-total-acordado').textContent =
            moneda.format(acordado);

        $('#rp-saldo-pendiente').textContent =
            moneda.format(acordado);

        $('#rp-diferencia-etiqueta').textContent =
            diferencia < 0n
                ? 'Descuento negociado'
                : diferencia > 0n
                    ? 'Incremento negociado'
                    : 'Diferencia negociada';

        $('#rp-diferencia').textContent =
            moneda.format(
                diferencia < 0n ? -diferencia : diferencia
            );

        let error = '';

        if (
            total > LIMITE_TOTAL ||
            acordado > LIMITE_TOTAL
        ) {

            error = 'La compra supera el valor máximo permitido.';

        } else if (
            productos > MAX_PRODUCTOS ||
            lineas > MAX_LINEAS ||
            tarjetas().some(t => filas(t).length > MAX_TIPOS)
        ) {

            error = 'Superaste el máximo de productos o presentaciones.';
        }

        $('#rp-error-total').textContent = error;
        $('#rp-error-total').hidden = !error;

        return !error;
    }

    // =====================================================
    // ACTUALIZACIÓN GENERAL
    // =====================================================

    function actualizar() {

        tarjetas().forEach(tarjeta => {

            const presentaciones = filas(tarjeta);

            presentaciones.forEach(fila => {

                actualizarPresentacion(tarjeta, fila);

                fila.querySelector('[data-eliminar-tipo]')
                    .disabled =
                        enviando ||
                        presentaciones.length <= 1;
            });

            tarjeta.querySelector('[data-agregar-tipo]')
                .disabled =
                    enviando ||
                    presentaciones.length >= MAX_TIPOS ||
                    totalLineas() >= MAX_LINEAS;

            // Nunca bloquear la eliminación por cantidad.

            tarjeta.querySelector('[data-eliminar-producto]')
                .disabled = enviando;
        });

        reindexar();
        validarDuplicados();
        actualizarProveedor();

        const valido = actualizarTotales();

        agregarProducto.disabled =
            !cargado ||
            enviando ||
            tarjetas().length >= MAX_PRODUCTOS ||
            totalLineas() >= MAX_LINEAS;

        guardar.disabled =
            !cargado ||
            enviando ||
            tarjetas().length === 0 ||
            !valido;

        return valido;
    }

    // =====================================================
    // RESTAURAR FORMULARIO
    // =====================================================

    function restaurarFormulario() {

        let anteriores = [];

        try {

            anteriores = comoLista(JSON.parse(
                modal.dataset.productosPrevios || '[]'
            ));

        } catch {

            anteriores = [];
        }

        lista.replaceChildren();

        if (anteriores.length) {

            anteriores.forEach(datos =>
                crearProducto(datos)
            );

        } else {

            crearProducto();
        }
    }

    // =====================================================
    // GENERAR DOCUMENTO INTERNO
    // =====================================================

    $('#rp-generar-documento')
        .addEventListener('click', () => {

            if (
                documento.value &&
                !confirm('¿Reemplazar la referencia actual?')
            ) return;

            if (window.crypto?.getRandomValues) {

                const aleatorio = new Uint32Array(1);

                window.crypto.getRandomValues(aleatorio);

                documento.value =
                    `INT-${10000000 + aleatorio[0] % 90000000}`;

            } else {

                documento.value =
                    $('#rp-generar-documento').dataset.referencia;
            }
        });

    // =====================================================
    // EVENTOS DE PRODUCTOS
    // =====================================================

    agregarProducto.addEventListener('click', () => {

        if (
            !cargado ||
            enviando ||
            tarjetas().length >= MAX_PRODUCTOS ||
            totalLineas() >= MAX_LINEAS
        ) return;

        const tarjeta = crearProducto();

        actualizar();

        selectorProducto(tarjeta).focus();
    });

    lista.addEventListener('change', evento => {

        const elemento = evento.target;
        const tarjeta = elemento.closest('.rp-product-card');

        if (!tarjeta) return;

        if (elemento.matches('[data-selector-producto]')) {

            const anterior = elemento.dataset.anterior || '';

            const tieneDatos = filas(tarjeta).some(fila =>
                Array.from(fila.querySelectorAll('[data-campo]'))
                    .some(input => input.value !== '')
            ) || campoProducto(tarjeta, 'nombre').value !== '';

            if (
                anterior &&
                anterior !== elemento.value &&
                tieneDatos
            ) {

                if (!confirm(
                    'Cambiar el producto eliminará sus presentaciones actuales. ¿Continuar?'
                )) {

                    elemento.value = anterior;

                    actualizar();

                    return;
                }
            }

            if (anterior !== elemento.value) {

                campoProducto(tarjeta, 'nombre').value = '';

                elemento.dataset.anterior = elemento.value;

                configurarProducto(tarjeta);

                tarjeta.querySelector('[data-contenedor-tipos]')
                    .replaceChildren();

                crearPresentacion(tarjeta);
            }

        } else if (elemento.matches('[data-selector-tipo]')) {

            const fila = elemento.closest('.rp-quality');

            nuevoTipo(fila).value = '';

            sincronizarTipo(tarjeta, fila);

            if (elemento.value === NUEVO_TIPO) {
                nuevoTipo(fila).focus();
            }
        }

        actualizar();
    });

    lista.addEventListener('input', evento => {

        if (!evento.target.matches('select')) {
            actualizar();
        }
    });

    usarAcordado.addEventListener('change', () => {

        if (
            usarAcordado.checked &&
            !valorAcordado.value
        ) {

            const total = calcularTotal();

            if (total > 0n && total <= LIMITE_TOTAL) {
                valorAcordado.value = total.toString();
            }
        }

        actualizar();

        if (usarAcordado.checked) {
            valorAcordado.focus();
        }
    });

    valorAcordado.addEventListener('input', actualizar);
    proveedor.addEventListener('change', actualizar);

    // =====================================================
    // GUARDAR COMPRA
    // =====================================================

    form.addEventListener('submit', evento => {

        if (
            enviando ||
            !cargado ||
            tarjetas().length === 0 ||
            !actualizar() ||
            !form.reportValidity()
        ) {

            evento.preventDefault();

            return;
        }

        enviando = true;

        guardar.disabled = true;
        agregarProducto.disabled = true;

        guardar.textContent = 'Registrando entrada...';

        actualizar();
    });

    modal.querySelectorAll('[data-rp-close]')
        .forEach(boton => {

            boton.addEventListener('click', () => {

                if (!enviando) modal.close();
            });
        });

    modal.addEventListener('cancel', evento => {

        if (enviando) evento.preventDefault();
    });

    window.addEventListener('pageshow', evento => {

        if (evento.persisted) {

            enviando = false;

            guardar.textContent =
                'Registrar entrada de mercancía';

            actualizar();
        }
    });

    // =====================================================
    // REGISTRO INDEPENDIENTE DE PROVEEDORES
    // =====================================================

    const modalProveedor = document.getElementById(
        'rp-modal-proveedor'
    );

    if (modalProveedor) {

        const formProveedor = modalProveedor.querySelector(
            '#rp-form-proveedor'
        );

        const guardarProveedor = modalProveedor.querySelector(
            '#rp-guardar-proveedor'
        );

        const cancelarProveedor = modalProveedor.querySelector(
            '#rp-cancelar-proveedor'
        );

        const errorProveedor = modalProveedor.querySelector(
            '#rp-error-proveedor'
        );

        let guardando = false;

        function mostrarError(mensaje = '') {

            errorProveedor.textContent = mensaje;
            errorProveedor.hidden = !mensaje;
        }

        $('#rp-nuevo-proveedor')
            .addEventListener('click', () => {

                mostrarError();

                if (!modalProveedor.open) {
                    modalProveedor.showModal();
                }
            });

        cancelarProveedor.addEventListener('click', () => {

            if (!guardando) modalProveedor.close();
        });

        modalProveedor.addEventListener('cancel', evento => {

            if (guardando) evento.preventDefault();
        });

        formProveedor.addEventListener(
            'submit',
            async evento => {

                evento.preventDefault();

                if (
                    guardando ||
                    !formProveedor.reportValidity()
                ) return;

                mostrarError();

                const datos = new FormData(formProveedor);

                guardando = true;

                guardarProveedor.disabled = true;
                cancelarProveedor.disabled = true;

                guardarProveedor.textContent = 'Guardando...';

                try {

                    const respuesta = await api('/proveedores', {

                        method: 'POST',

                        body: JSON.stringify({

                            nombre: limpiar(datos.get('nombre')),

                            telefono:
                                limpiar(datos.get('telefono')) || null,

                            contacto:
                                limpiar(datos.get('contacto')) || null,

                            consentimiento:
                                datos.get('consentimiento') === '1'
                        })
                    });

                    if (
                        !respuesta?.ok ||
                        !respuesta.proveedor?.id
                    ) {

                        mostrarError(
                            respuesta?.mensaje ||
                            respuesta?.message ||
                            'No se pudo registrar el proveedor.'
                        );

                        return;
                    }

                    const nuevo = respuesta.proveedor;
                    const id = String(nuevo.id);

                    const existente = Array.from(proveedor.options)
                        .find(o => o.value === id);

                    if (!existente) {
                        proveedor.add(
                            new Option(nuevo.nombre, id)
                        );
                    }

                    proveedor.value = id;

                    formProveedor.reset();
                    modalProveedor.close();

                    actualizar();

                } catch (error) {

                    if (error.message !== 'sin sesion') {

                        mostrarError(
                            'No se pudo confirmar el registro del proveedor.'
                        );
                    }

                } finally {

                    guardando = false;

                    guardarProveedor.disabled = false;
                    cancelarProveedor.disabled = false;

                    guardarProveedor.textContent =
                        'Guardar proveedor';
                }
            }
        );
    }

    // =====================================================
    // INICIALIZACIÓN
    // =====================================================

    if (
        modal.dataset.reabrir === '1' &&
        !modal.open
    ) {

        modal.showModal();
    }

    cargarCatalogo();

});