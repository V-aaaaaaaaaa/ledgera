// ==================== CONFIGURACIÓN ====================
const API = '/ledgera/api';
const PAGINA_LOGIN = '/ledgera/index.html';
const PAGINA_DASHBOARD = '/ledgera/dashboard.html';


// ==================== ESTADO ====================
let productos = [];
let productoSeleccionado = null;
let fiadoGuardado = false;
let fiadoData = null;
let ventaCompletada = false;

// ==================== ELEMENTOS DOM ====================
const productoSelector = document.getElementById('productoSelector');
const productoDisplay = document.getElementById('productoDisplay');
const productoList = document.getElementById('productoList');
const productoSearch = document.getElementById('productoSearch');
const ventaCantidad = document.getElementById('ventaCantidad');
const ventaPrecioSugerido = document.getElementById('ventaPrecioSugerido');
const ventaPrecio = document.getElementById('ventaPrecio');
const stockHint = document.getElementById('stockHint');
const metodoPago = document.getElementById('metodoPago');
const fiadoBlock = document.getElementById('fiadoBlock');
const fiadoNombre = document.getElementById('fiadoNombre');
const fiadoTelefono = document.getElementById('fiadoTelefono');
const fiadoAbono = document.getElementById('fiadoAbono');
const fiadoSaldo = document.getElementById('fiadoSaldo');
const fiadoVencimiento = document.getElementById('fiadoVencimiento');
const btnGuardarFiado = document.getElementById('btnGuardarFiado');
const resumenSubtotal = document.getElementById('resumenSubtotal');
const resumenDeuda = document.getElementById('resumenDeuda');
const resumenDeudaRow = document.getElementById('resumenDeudaRow');
const resumenTotal = document.getElementById('resumenTotal');
const btnCompletar = document.getElementById('btnCompletar');
const reciboVacio = document.getElementById('reciboVacio');
const recibo = document.getElementById('recibo');

// ==================== UTILIDADES ====================
function formatoMoneda(valor) {
    return '$' + Number(valor || 0).toLocaleString('es-CO');
}

function formatoFecha(fechaISO) {
    const d = new Date(fechaISO);
    return d.toLocaleDateString('es-CO', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

// ==================== CARGAR PRODUCTOS ====================
async function cargarProductos() {
    try {
        const res = await fetch(`${API_URL}/productos`, {
            headers: { 'Authorization': `Bearer ${TOKEN}` }
        });
        if (!res.ok) throw new Error('Error al cargar productos');
        productos = await res.json();
    } catch (err) {
        console.warn('Modo demo:', err.message);
        productos = [
            { id: 1, nombre: 'Arroz 500g',    precio: 2500, stock: 40 },
            { id: 2, nombre: 'Aceite 1L',     precio: 8500, stock: 3  },  // stock bajo
            { id: 3, nombre: 'Leche 1L',      precio: 3200, stock: 0  },  // sin stock
            { id: 4, nombre: 'Pan tajado',    precio: 4500, stock: 15 },
            { id: 5, nombre: 'Huevos x12',    precio: 9800, stock: 2  },  // stock bajo
            { id: 6, nombre: 'Café 250g',     precio: 12000, stock: 8 }
        ];
    }
    renderProductos(productos);
}

// ==================== RENDER PRODUCTOS ====================
function renderProductos(lista) {
    productoList.innerHTML = '';

    if (lista.length === 0) {
        productoList.innerHTML = '<div class="producto-item" style="justify-content:center;color:#8aa88a;">Sin resultados</div>';
        return;
    }

    lista.forEach(p => {
        const sinStock = p.stock === 0;
        const stockBajo = p.stock > 0 && p.stock <= 5;

        const item = document.createElement('div');
        item.className = 'producto-item' + (sinStock ? ' sin-stock' : '');
        item.dataset.id = p.id;

        let stockClase = 'ok';
        let stockTexto = `${p.stock} en stock`;
        if (sinStock) { stockClase = 'cero'; stockTexto = 'Sin stock'; }
        else if (stockBajo) { stockClase = 'bajo'; stockTexto = `${p.stock} (stock bajo)`; }

        item.innerHTML = `
            <div class="p-info">
                <span class="p-nombre">${p.nombre}</span>
                <span class="p-meta">
                    <span class="p-stock ${stockClase}">${stockTexto}</span>
                </span>
            </div>
            <span class="p-precio">${formatoMoneda(p.precio)}</span>
        `;

        if (!sinStock) {
            item.addEventListener('click', () => seleccionarProducto(p));
        }

        productoList.appendChild(item);
    });
}

// ==================== SELECCIONAR PRODUCTO ====================
function seleccionarProducto(p) {
    productoSeleccionado = p;

    productoDisplay.innerHTML = `
        <span>${p.nombre} — ${formatoMoneda(p.precio)}</span>
        <i class="fas fa-chevron-down"></i>
    `;
    productoDisplay.classList.remove('placeholder');
    productoSelector.classList.remove('open');

    ventaPrecioSugerido.value = p.precio;
    ventaPrecio.value = p.precio;
    ventaCantidad.value = 1;

    actualizarHintStock();
    actualizarResumen();
}

function actualizarHintStock() {
    if (!productoSeleccionado) {
        stockHint.textContent = '';
        return;
    }
    const p = productoSeleccionado;
    const stockBajo = p.stock > 0 && p.stock <= 5;

    if (p.stock === 0) {
        stockHint.textContent = 'Producto sin stock disponible';
        stockHint.className = 'hint danger';
    } else if (stockBajo) {
        stockHint.textContent = `¡Stock bajo! Solo quedan ${p.stock} unidades`;
        stockHint.className = 'hint danger';
    } else {
        stockHint.textContent = `Disponible: ${p.stock} unidades`;
        stockHint.className = 'hint';
    }
}

// ==================== ABRIR/CERRAR DROPDOWN PRODUCTO ====================
productoDisplay.addEventListener('click', () => {
    productoSelector.classList.toggle('open');
    if (productoSelector.classList.contains('open')) {
        productoSearch.value = '';
        renderProductos(productos);
        setTimeout(() => productoSearch.focus(), 100);
    }
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('#productoSelector')) {
        productoSelector.classList.remove('open');
    }
});

productoSearch.addEventListener('input', (e) => {
    const q = e.target.value.toLowerCase().trim();
    const filtrados = productos.filter(p => p.nombre.toLowerCase().includes(q));
    renderProductos(filtrados);
});

// ==================== CANTIDAD ====================
document.getElementById('btnQtyPlus').addEventListener('click', () => {
    const max = productoSeleccionado ? productoSeleccionado.stock : 9999;
    const actual = parseInt(ventaCantidad.value) || 1;
    if (actual < max) ventaCantidad.value = actual + 1;
    actualizarResumen();
});

document.getElementById('btnQtyMinus').addEventListener('click', () => {
    const actual = parseInt(ventaCantidad.value) || 1;
    if (actual > 1) ventaCantidad.value = actual - 1;
    actualizarResumen();
});

ventaCantidad.addEventListener('input', () => {
    let v = parseInt(ventaCantidad.value) || 1;
    if (v < 1) v = 1;
    if (productoSeleccionado && v > productoSeleccionado.stock) {
        v = productoSeleccionado.stock || 1;
    }
    ventaCantidad.value = v;
    actualizarResumen();
});

// ==================== PRECIO ====================
ventaPrecio.addEventListener('input', actualizarResumen);

// ==================== MÉTODO DE PAGO ====================
metodoPago.addEventListener('change', () => {
    const metodo = metodoPago.value;
    const mostrarFiado = metodo === 'credito' || metodo === 'mixto';

    fiadoBlock.style.display = mostrarFiado ? 'block' : 'none';
    resumenDeudaRow.style.display = mostrarFiado ? 'flex' : 'none';

    if (!mostrarFiado) {
        fiadoGuardado = false;
        fiadoData = null;
    }

    actualizarResumen();
});

// ==================== FIADO ====================
fiadoAbono.addEventListener('input', actualizarSaldoFiado);

function actualizarSaldoFiado() {
    const total = calcularTotal();
    const abono = Number(fiadoAbono.value) || 0;
    const saldo = Math.max(0, total - abono);
    fiadoSaldo.value = saldo;
    resumenDeuda.textContent = formatoMoneda(saldo);
    return saldo;
}

btnGuardarFiado.addEventListener('click', () => {
    const nombre = fiadoNombre.value.trim();
    const telefono = fiadoTelefono.value.trim();
    const abono = Number(fiadoAbono.value) || 0;
    const vencimiento = fiadoVencimiento.value;
    const total = calcularTotal();
    const saldo = Math.max(0, total - abono);

    if (!nombre) return alert('Ingresa el nombre del cliente');
    if (!telefono) return alert('Ingresa el teléfono del cliente');
    if (!vencimiento) return alert('Selecciona la fecha de vencimiento');
    if (abono > total) return alert('El abono no puede ser mayor al total');

    fiadoData = {
        nombre,
        telefono,
        abono,
        saldo,
        vencimiento
    };
    fiadoGuardado = true;

    btnGuardarFiado.innerHTML = '<i class="fas fa-check"></i> Fiado guardado';
    btnGuardarFiado.classList.add('guardado');

    setTimeout(() => {
        btnGuardarFiado.innerHTML = '<i class="fas fa-save"></i> Guardar fiado';
        btnGuardarFiado.classList.remove('guardado');
    }, 2500);