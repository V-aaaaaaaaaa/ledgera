'use strict';

document.addEventListener('DOMContentLoaded', () => {

    const modal = document.querySelector('.rp-modal');

    if (!modal) return;

    // =====================================================
    // ELEMENTOS DEL FORMULARIO
    // =====================================================

    const form = modal.querySelector('#rp-form');
    const lista = modal.querySelector('#rp-productos');

    const plantillaProducto = modal.querySelector('#rp-template-producto');
    const plantillaTipo = modal.querySelector('#rp-template-tipo');

    const agregarProducto = modal.querySelector('#rp-agregar-producto');
    const guardar = modal.querySelector('#rp-guardar');

    const selectorProveedor = modal.querySelector('#rp-proveedor');
    const sugerenciaProveedor = modal.querySelector('#rp-sugerencia-proveedor');
    const sinProveedores = modal.querySelector('#rp-sin-proveedores');

    const documento = modal.querySelector('#rp-documento');
    const generarDocumento = modal.querySelector('#rp-generar-documento');

    const estadoCarga = modal.querySelector('#rp-estado-carga');
    const avisoTotal = modal.querySelector('#rp-error-total');

    const LIMITE_TOTAL = 9999999999n;
    const MAX_PRODUCTOS = 20;
    const MAX_TIPOS = 20;
    const MAX_TIPOS_TOTAL = 200;

    const moneda = new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    });

    const numero = new Intl.NumberFormat('es-CO');

    let catalogo = [];
    let indiceProducto = 0;
    let catalogoCargado = false;
    let enviando = false;

    // =====================================================
    // FUNCIONES AUXILIARES
    // =====================================================

    const texto = valor =>
        typeof valor === 'string' || typeof valor === 'number'
            ? String(valor)
            : '';

    const normalizar = valor =>
        texto(valor)
            .trim()
            .replace(/\s+/g, ' ')
            .toLocaleLowerCase('es-CO');

    const entero = input =>
        /^\d+$/.test(input.value)
            ? BigInt(input.value)
            : 0n;

    const comoLista = valor =>
        valor && typeof valor === 'object'
            ? Object.values(valor).filter(
                elemento => elemento && typeof elemento === 'object'
            )
            : [];

    const tarjetas = () =>
        Array.from(lista.querySelectorAll('.rp-product-card'));

    const totalTipos = () =>
        lista.querySelectorAll('.rp-quality').length;

    function buscarProducto(id) {

        return catalogo.find(
            producto => String(producto.id_producto) === String(id)
        );

    }

    function productoSeleccionado(tarjeta) {

        return buscarProducto(
            tarjeta.querySelector('[data-selector-producto]').value
        );

    }

    function buscarTipo(producto, nombre) {

        return producto?.tipos?.find(
            tipo => normalizar(tipo.nombre) === normalizar(nombre)
        );

    }

    // =====================================================
    // CARGAR CATÁLOGO DESDE LARAVEL
    // =====================================================

    async function cargarCatalogo() {

        estadoCarga.hidden = false;
        estadoCarga.textContent = 'Cargando catálogo de productos...';

        try {

            const respuesta = await api('/entradas-mercancia/datos');

            if (!respuesta?.ok || !Array.isArray(respuesta.productos)) {

                throw new Error(
                    respuesta?.mensaje ||
                    'No fue posible cargar los productos.'
                );

            }

            catalogo = respuesta.productos;
            catalogoCargado = true;

            estadoCarga.hidden = true;

            restaurarFormulario();

            actualizar();

        } catch (error) {

            catalogoCargado = false;

            if (error.message === 'sin sesion') return;

            estadoCarga.textContent =
                'No se pudo cargar el catálogo. Verifica tu conexión y recarga la página.';

            actualizar();

        }

    }

    // =====================================================
    // AGREGAR PRODUCTOS
    // =====================================================

    function agregarTarjeta(datos = {}) {

        if (tarjetas().length >= MAX_PRODUCTOS) return;

        const tarjeta = plantillaProducto.content
            .firstElementChild
            .cloneNode(true);

        const indice = indiceProducto++;

        tarjeta.dataset.indice = indice;
        tarjeta.dataset.indiceTipo = '0';

        const selector = tarjeta.querySelector(
            '[data-selector-producto]'
        );

        const listaTipos = tarjeta.querySelector(
            '[data-lista-tipos]'
        );

        listaTipos.id = `rp-lista-tipos-${indice}`;

        // Cargar productos existentes.

        catalogo.forEach(producto => {

            selector.add(new Option(
                producto.nombre_producto,
                String(producto.id_producto)
            ));

        });

        // Construir nombres de campos para Laravel.

        tarjeta.querySelectorAll('[data-producto-campo]')
            .forEach(input => {

                const campo = input.dataset.productoCampo;

                input.name = `productos[${indice}][${campo}]`;

                if (datos[campo] != null) {
                    input.value = texto(datos[campo]);
                }

            });

        lista.appendChild(tarjeta);

        // Recuperar selección después de errores.

        if (datos.id_producto) {

            const id = String(datos.id_producto);

            selector.value = buscarProducto(id) ? id : '';

        } else if (datos.nombre || datos.unidad_comercial) {

            selector.value = 'nuevo';

        }

        configurarProducto(tarjeta);

        // Recuperar tipos previamente ingresados.

        const tiposAnteriores = comoLista(datos.calidades);

        if (tiposAnteriores.length) {

            tiposAnteriores
                .slice(0, MAX_TIPOS)
                .forEach(tipo => agregarTipo(tarjeta, tipo));

        } else {

            agregarTipo(tarjeta);

        }

        selector.dataset.anterior = selector.value;

        actualizar();

        return tarjeta;

    }

    // =====================================================
    // CONFIGURAR PRODUCTO EXISTENTE O NUEVO
    // =====================================================

    function configurarProducto(tarjeta) {

        const selector = tarjeta.querySelector(
            '[data-selector-producto]'
        );

        const producto = productoSeleccionado(tarjeta);
        const esNuevo = selector.value === 'nuevo';

        const idProducto = tarjeta.querySelector(
            '[data-producto-campo="id_producto"]'
        );

        const nombre = tarjeta.querySelector(
            '[data-producto-campo="nombre"]'
        );

        const descripcion = tarjeta.querySelector(
            '[data-producto-campo="descripcion"]'
        );

        const unidad = tarjeta.querySelector(
            '[data-producto-campo="unidad_comercial"]'
        );

        const informacion = tarjeta.querySelector(
            '[data-info-producto]'
        );

        const listaTipos = tarjeta.querySelector(
            '[data-lista-tipos]'
        );

        idProducto.value = producto?.id_producto || '';

        // Mostrar nombre y descripción solo para productos nuevos.

        tarjeta.querySelector('[data-nombre-nuevo]').hidden =
            !esNuevo;

        tarjeta.querySelector('[data-descripcion-nueva]').hidden =
            !esNuevo;

        nombre.disabled = !esNuevo;
        nombre.required = esNuevo;

        descripcion.disabled = !esNuevo;

        // Restablecer opciones de unidad comercial.

        Array.from(unidad.options).forEach(opcion => {
            opcion.disabled = false;
        });

        listaTipos.replaceChildren();

        informacion.hidden = true;
        informacion.textContent = '';

        if (producto) {

            // Mantener la unidad comercial existente.

            if (producto.unidad_comercial) {

                unidad.value = producto.unidad_comercial;

                Array.from(unidad.options).forEach(opcion => {

                    opcion.disabled =
                        opcion.value !== producto.unidad_comercial;

                });

                informacion.textContent =
                    'Producto existente. Se conservará su unidad comercial.';

            } else {

                informacion.textContent =
                    'Este producto todavía no tiene unidad comercial. Selecciona la correspondiente.';

            }

            informacion.hidden = false;

            // Cargar sugerencias de tipos existentes.

            (producto.tipos || []).forEach(tipo => {

                const opcion = document.createElement('option');

                opcion.value = tipo.nombre;

                opcion.label =
                    `Stock actual: ${numero.format(tipo.stock)}`;

                listaTipos.appendChild(opcion);

            });

        }

        if (esNuevo) {

            informacion.textContent =
                'Se creará el producto y sus tipos con esta entrada.';

            informacion.hidden = false;

        }

        tarjeta.querySelectorAll('.rp-quality').forEach(fila => {

            configurarTipo(tarjeta, fila);

        });

        actualizarTitulos();

    }

    // =====================================================
    // AGREGAR TIPOS DE PRODUCTO
    // =====================================================

    function agregarTipo(tarjeta, datos = {}) {

        const contenedor = tarjeta.querySelector(
            '[data-contenedor-tipos]'
        );

        if (contenedor.children.length >= MAX_TIPOS) return;
        if (totalTipos() >= MAX_TIPOS_TOTAL) return;

        const fila = plantillaTipo.content
            .firstElementChild
            .cloneNode(true);

        const indice = tarjeta.dataset.indice;

        const indiceTipo = Number(tarjeta.dataset.indiceTipo);

        tarjeta.dataset.indiceTipo = String(indiceTipo + 1);

        fila.querySelectorAll('[data-campo]').forEach(input => {

            const campo = input.dataset.campo;

            input.name =
                `productos[${indice}][calidades][${indiceTipo}][${campo}]`;

            input.value = texto(datos[campo]);

        });

        const nombre = fila.querySelector('[data-campo="nombre"]');

        nombre.setAttribute(
            'list',
            `rp-lista-tipos-${indice}`
        );

        contenedor.appendChild(fila);

        configurarTipo(tarjeta, fila);

        actualizar();

        return fila;

    }

    // =====================================================
    // CONFIGURAR TIPO EXISTENTE O NUEVO
    // =====================================================

    function configurarTipo(tarjeta, fila) {

        const producto = productoSeleccionado(tarjeta);

        const nombre = fila.querySelector(
            '[data-campo="nombre"]'
        );

        const precio = fila.querySelector(
            '[data-campo="precio"]'
        );

        const stock = fila.querySelector(
            '[data-stock-tipo]'
        );

        const infoPrecio = fila.querySelector(
            '[data-info-precio]'
        );

        const tipo = buscarTipo(producto, nombre.value);

        if (tipo) {

            // Un ingreso de mercancía no cambia el precio existente.

            precio.value = '';
            precio.disabled = true;
            precio.required = false;

            precio.placeholder = 'Precio vigente';

            stock.textContent =
                `Existente · Stock: ${numero.format(tipo.stock)}`;

            infoPrecio.textContent =
                `Actual: ${moneda.format(tipo.precio)}`;

        } else {

            precio.disabled = false;

            precio.required = Boolean(nombre.value.trim());

            precio.placeholder = 'Ej. 50000';

            stock.textContent = nombre.value.trim()
                ? 'Nuevo tipo'
                : '';

            infoPrecio.textContent = nombre.value.trim()
                ? 'Define el precio de venta.'
                : '';

        }

    }

    // =====================================================
    // SUGERENCIA DE PROVEEDORES
    // =====================================================

    function sugerirProveedor(seleccionarAutomaticamente = false) {

        const sugeridos = new Set();

        tarjetas().forEach(tarjeta => {

            const producto = productoSeleccionado(tarjeta);

            const idProveedor = producto?.ultimo_proveedor;

            if (!idProveedor) return;

            const existe = Array.from(selectorProveedor.options)
                .some(opcion => opcion.value === String(idProveedor));

            if (existe) sugeridos.add(String(idProveedor));

        });

        sugerenciaProveedor.hidden = true;
        sugerenciaProveedor.textContent = '';

        if (sugeridos.size === 0) return;

        sugerenciaProveedor.hidden = false;

        if (sugeridos.size > 1) {

            sugerenciaProveedor.textContent =
                'Los productos tienen distintos proveedores habituales. Selecciona el proveedor de esta compra.';

            return;

        }

        const idSugerido = Array.from(sugeridos)[0];

        const opcion = Array.from(selectorProveedor.options)
            .find(item => item.value === idSugerido);

        if (!selectorProveedor.value && seleccionarAutomaticamente) {

            selectorProveedor.value = idSugerido;

        }

        // Nunca reemplazar un proveedor elegido por el operador.

        sugerenciaProveedor.textContent =
            selectorProveedor.value === idSugerido
                ? `Último proveedor utilizado: ${opcion.text}`
                : `Proveedor sugerido: ${opcion.text}. Puedes seleccionar otro.`;

    }

    // =====================================================
    // GENERAR REFERENCIA INTERNA
    // =====================================================

    generarDocumento.addEventListener('click', () => {

        if (
            documento.value.trim() &&
            !/^INT-\d+$/.test(documento.value.trim()) &&
            !confirm('¿Reemplazar el documento por una referencia interna?')
        ) {

            return;

        }

        let referencia;

        do {

            const aleatorio = new Uint32Array(1);

            crypto.getRandomValues(aleatorio);

            const codigo = 10000000 + (aleatorio[0] % 90000000);

            referencia = `INT-${codigo}`;

        } while (referencia === documento.value);

        documento.value = referencia;

        documento.focus();

    });

    // =====================================================
    // CÁLCULOS FINANCIEROS
    // =====================================================

    function actualizarTitulos() {

        tarjetas().forEach((tarjeta, indice) => {

            const producto = productoSeleccionado(tarjeta);

            const nombre = tarjeta.querySelector(
                '[data-producto-campo="nombre"]'
            ).value;

            tarjeta.querySelector('[data-titulo-producto]')
                .textContent =
                    `Producto ${indice + 1} · ${
                        producto?.nombre_producto ||
                        nombre.trim() ||
                        'Sin seleccionar'
                    }`;

        });

    }

    function actualizar() {

        let total = 0n;
        let cantidadTipos = 0;

        const productos = tarjetas();

        productos.forEach(tarjeta => {

            let subtotalProducto = 0n;

            const filas = Array.from(
                tarjeta.querySelectorAll('.rp-quality')
            );

            cantidadTipos += filas.length;

            filas.forEach(fila => {

                const cantidad = fila.querySelector(
                    '[data-campo="cantidad"]'
                );

                const costo = fila.querySelector(
                    '[data-campo="costo"]'
                );

                fila.querySelectorAll('input[type="number"]')
                    .forEach(input => {

                        input.setCustomValidity(
                            input.value && !/^\d+$/.test(input.value)
                                ? 'Ingresa un número entero.'
                                : ''
                        );

                    });

                const subtotal =
                    entero(cantidad) * entero(costo);

                subtotalProducto += subtotal;

                fila.querySelector('[data-subtotal]')
                    .textContent = moneda.format(subtotal);

                fila.querySelector('[data-eliminar-tipo]')
                    .disabled = filas.length <= 1;

            });

            total += subtotalProducto;

            tarjeta.querySelector('[data-total-producto]')
                .textContent = moneda.format(subtotalProducto);

            tarjeta.querySelector('[data-eliminar-producto]')
                .disabled = productos.length <= 1;

            tarjeta.querySelector('[data-agregar-tipo]')
                .disabled =
                    filas.length >= MAX_TIPOS ||
                    totalTipos() >= MAX_TIPOS_TOTAL;

        });

        modal.querySelector('#rp-total-productos')
            .textContent = numero.format(productos.length);

        modal.querySelector('#rp-total-tipos')
            .textContent = `${cantidadTipos} tipos`;

        modal.querySelector('#rp-total')
            .textContent = moneda.format(total);

        const proveedoresDisponibles =
            selectorProveedor.options.length > 1;

        sinProveedores.hidden = proveedoresDisponibles;

        avisoTotal.hidden = total <= LIMITE_TOTAL;

        agregarProducto.disabled =
            !catalogoCargado ||
            productos.length >= MAX_PRODUCTOS ||
            cantidadTipos >= MAX_TIPOS_TOTAL;

        guardar.disabled =
            !catalogoCargado ||
            !proveedoresDisponibles ||
            productos.length === 0 ||
            total > LIMITE_TOTAL ||
            enviando;

        actualizarTitulos();

    }

    // =====================================================
    // EVENTOS DINÁMICOS
    // =====================================================

    agregarProducto.addEventListener('click', () => {

        const tarjeta = agregarTarjeta();

        tarjeta?.querySelector(
            '[data-selector-producto]'
        ).focus();

    });

    lista.addEventListener('change', event => {

        const selector = event.target.closest(
            '[data-selector-producto]'
        );

        if (!selector) return;

        const tarjeta = selector.closest('.rp-product-card');

        const anterior = selector.dataset.anterior || '';

        if (anterior !== selector.value && anterior !== '') {

            const tieneDatos = Array.from(
                tarjeta.querySelectorAll('.rp-quality input')
            ).some(input => input.value.trim() !== '');

            if (
                tieneDatos &&
                !confirm('¿Cambiar el producto? Se reiniciarán sus tipos y cantidades.')
            ) {

                selector.value = anterior;

                return;

            }

            tarjeta.querySelector('[data-contenedor-tipos]')
                .replaceChildren();

            tarjeta.querySelector('[data-producto-campo="nombre"]')
                .value = '';

            tarjeta.querySelector('[data-producto-campo="descripcion"]')
                .value = '';

            tarjeta.querySelector('[data-producto-campo="unidad_comercial"]')
                .value = '';

            configurarProducto(tarjeta);

            agregarTipo(tarjeta);

        } else {

            configurarProducto(tarjeta);

        }

        selector.dataset.anterior = selector.value;

        sugerirProveedor(true);

        actualizar();

    });

    lista.addEventListener('input', event => {

        const tarjeta = event.target.closest('.rp-product-card');

        const fila = event.target.closest('.rp-quality');

        if (tarjeta && fila && event.target.dataset.campo === 'nombre') {

            configurarTipo(tarjeta, fila);

        }

        actualizar();

    });

    lista.addEventListener('click', event => {

        const tarjeta = event.target.closest('.rp-product-card');

        if (!tarjeta) return;

        // Agregar tipo.

        if (event.target.closest('[data-agregar-tipo]')) {

            const fila = agregarTipo(tarjeta);

            fila?.querySelector('[data-campo="nombre"]').focus();

        }

        // Eliminar tipo.

        const eliminarTipo = event.target.closest(
            '[data-eliminar-tipo]'
        );

        if (eliminarTipo && !eliminarTipo.disabled) {

            eliminarTipo.closest('.rp-quality').remove();

        }

        // Eliminar producto.

        const eliminarProducto = event.target.closest(
            '[data-eliminar-producto]'
        );

        if (eliminarProducto && !eliminarProducto.disabled) {

            tarjeta.remove();

            sugerirProveedor();

        }

        actualizar();

    });

    selectorProveedor.addEventListener('change', () => {

        sugerirProveedor();

        actualizar();

    });

    // =====================================================
    // REGISTRO INTEGRADO DE PROVEEDORES
    // =====================================================

    const nuevoProveedor = modal.querySelector(
        '#rp-nuevo-proveedor'
    );

    const modalProveedor = document.getElementById(
        'rp-modal-proveedor'
    );

    if (nuevoProveedor && modalProveedor) {

        const formProveedor = modalProveedor.querySelector(
            '#rp-form-proveedor'
        );

        const errorProveedor = modalProveedor.querySelector(
            '#rp-error-proveedor'
        );

        const btnGuardar = modalProveedor.querySelector(
            '#rp-guardar-proveedor'
        );

        const btnCancelar = modalProveedor.querySelector(
            '#rp-cancelar-proveedor'
        );

        nuevoProveedor.addEventListener('click', () => {

            formProveedor.reset();

            errorProveedor.hidden = true;

            modalProveedor.showModal();

        });

        btnCancelar.addEventListener('click', () => {

            modalProveedor.close();

        });

        modalProveedor.addEventListener('cancel', event => {

            if (btnGuardar.disabled) {

                event.preventDefault();

            }

        });

        formProveedor.addEventListener('submit', async event => {

            event.preventDefault();

            if (btnGuardar.disabled) return;

            const datos = new FormData(formProveedor);

            errorProveedor.hidden = true;

            btnGuardar.disabled = true;
            btnCancelar.disabled = true;

            try {

                const respuesta = await api('/proveedores', {

                    method: 'POST',

                    body: JSON.stringify({

                        nombre: texto(datos.get('nombre')).trim(),

                        telefono:
                            texto(datos.get('telefono')).trim() || null,

                        contacto:
                            texto(datos.get('contacto')).trim() || null,

                        consentimiento:
                            datos.has('consentimiento')

                    })

                });

                if (!respuesta?.ok || !respuesta.proveedor) {

                    throw new Error(
                        respuesta?.mensaje ||
                        'No se pudo registrar el proveedor.'
                    );

                }

                const proveedor = respuesta.proveedor;

                const existe = Array.from(selectorProveedor.options)
                    .some(opcion => opcion.value === String(proveedor.id));

                if (!existe) {

                    selectorProveedor.add(
                        new Option(
                            proveedor.nombre,
                            String(proveedor.id)
                        )
                    );

                }

                selectorProveedor.value = String(proveedor.id);

                modalProveedor.close();

                sugerirProveedor();

                actualizar();

                selectorProveedor.focus();

            } catch (error) {

                if (error.message !== 'sin sesion') {

                    errorProveedor.textContent = error.message;

                    errorProveedor.hidden = false;

                }

            } finally {

                btnGuardar.disabled = false;

                btnCancelar.disabled = false;

            }

        });

    }

    // =====================================================
    // RECUPERAR FORMULARIO ANTE ERRORES
    // =====================================================

    function restaurarFormulario() {

        let anteriores = [];

        try {

            anteriores = comoLista(
                JSON.parse(modal.dataset.productosPrevios || '[]')
            );

        } catch {

            anteriores = [];

        }

        if (anteriores.length) {

            anteriores.slice(0, MAX_PRODUCTOS)
                .forEach(producto => agregarTarjeta(producto));

        } else {

            agregarTarjeta();

        }

        sugerirProveedor();

    }

    // =====================================================
    // PREVENIR DOBLE ENVÍO
    // =====================================================

    form.addEventListener('submit', event => {

        if (enviando || guardar.disabled) {

            event.preventDefault();

            return;

        }

        enviando = true;

        guardar.disabled = true;

        guardar.textContent = 'Registrando mercancía...';

    });

    // =====================================================
    // CIERRE E INICIALIZACIÓN
    // =====================================================

    modal.querySelectorAll('[data-rp-close]').forEach(boton => {

        boton.addEventListener('click', () => {

            if (!enviando) modal.close();

        });

    });

    modal.addEventListener('cancel', event => {

        if (enviando) event.preventDefault();

    });

    cargarCatalogo();

    if (modal.dataset.reabrir === '1' && !modal.open) {

        modal.showModal();

    }

});