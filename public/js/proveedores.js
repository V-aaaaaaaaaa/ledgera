// =====================================================
// LEDGERA · Dueño · Proveedores
// Habla con ProveedorController (rutas /proveedores...).
// =====================================================
const RUTA = '/proveedores';

let proveedores = [];

const $ = (id) => document.getElementById(id);
const tbody = $('tablaProveedores');
const modal = $('modalProveedor');
const form = $('formProveedor');

// ==================== UTILIDADES ====================
// Escapa el texto antes de pintarlo (evita que un nombre con <script> se ejecute)
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function mensaje(id, texto) {
    const el = $(id);
    if (!el || !texto) return;
    el.textContent = texto;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

const abrir = () => modal.classList.add('open');
const cerrar = () => modal.classList.remove('open');
const cerrarMenus = () =>
    document.querySelectorAll('.dropdown-menu.open').forEach((m) => m.classList.remove('open'));

// Llama al servidor. Si no hay conexión devuelve un mensaje claro en vez de fallar.
async function llamar(ruta, metodo = 'GET', cuerpo) {
    try {
        return await api(RUTA + ruta, {
            method: metodo,
            body: cuerpo ? JSON.stringify(cuerpo) : undefined
        });
    } catch (e) {
        if (e.message === 'sin sesion') return { ok: false, mensaje: '' };
        return { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }
}

// ==================== TABLA ====================
function pintarTabla() {
    tbody.innerHTML = '';
    $('emptyState').style.display = proveedores.length ? 'none' : 'block';

    proveedores.forEach((p) => {
        const activo = p.estado === 'Activo';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                ${esc(p.nombre)}
                <span class="badge ${activo ? 'active' : 'inactive'}">${activo ? 'Activo' : 'Inactivo'}</span>
            </td>
            <td>${p.telefono ? `<a class="tel-link" href="tel:${esc(p.telefono)}">${esc(p.telefono)}</a>` : '—'}</td>
            <td>${esc(p.contacto || '—')}</td>
            <td class="col-actions">
                <div class="menu-container">
                    <button class="btn-dots" data-id="${p.id}" title="Opciones">
                        <i class="fas fa-ellipsis-vertical"></i>
                    </button>
                    <div class="dropdown-menu" data-menu-id="${p.id}">
                        <button class="dropdown-item" data-action="editar" data-id="${p.id}">
                            <i class="fas fa-pen"></i> Editar
                        </button>
                        <button class="dropdown-item ${activo ? 'danger' : ''}" data-action="estado" data-id="${p.id}">
                            <i class="fas fa-${activo ? 'ban' : 'check'}"></i> ${activo ? 'Desactivar' : 'Activar'}
                        </button>
                    </div>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });
}

async function cargar() {
    const data = await llamar('/datos');
    if (!data.ok) { if (data.mensaje) alert(data.mensaje); return; }
    proveedores = data.proveedores;
    pintarTabla();
}

// ==================== CREAR / EDITAR (misma ventana) ====================
function abrirFormulario(p = null) {
    form.reset();
    $('provId').value = p ? p.id : '';
    $('modalTitulo').textContent = p ? 'Editar proveedor' : 'Nuevo proveedor';
    $('provNombre').value = p ? p.nombre : '';
    $('provTelefono').value = p?.telefono || '';
    $('provContacto').value = p?.contacto || '';

    // Si ya autorizó antes, no se vuelve a pedir
    $('filaConsentimiento').style.display = p && p.consentimiento ? 'none' : '';
    abrir();
    $('provNombre').focus();
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const id = $('provId').value;
    const pideConsentimiento = $('filaConsentimiento').style.display !== 'none';

    // Revisión rápida antes de ir al servidor (el servidor vuelve a validar todo)
    if (!$('provNombre').value.trim()) return mensaje('provError', 'Escribe el nombre del proveedor.');
    if (pideConsentimiento && !$('provConsentimiento').checked) {
        return mensaje('provError', 'Confirma que el proveedor autorizó el tratamiento de sus datos.');
    }

    $('btnGuardar').disabled = true;   // evita doble registro por doble toque

    const data = await llamar(id ? '/' + id : '', id ? 'PUT' : 'POST', {
        nombre: $('provNombre').value.trim(),
        telefono: $('provTelefono').value.trim() || null,
        contacto: $('provContacto').value.trim() || null,
        consentimiento: $('provConsentimiento').checked
    });

    $('btnGuardar').disabled = false;
    if (!data.ok) return mensaje('provError', data.mensaje);

    mensaje('provExito', data.mensaje);
    await cargar();
    setTimeout(cerrar, 600);
});

// ==================== ACTIVAR / DESACTIVAR ====================
async function cambiarEstado(p) {
    const activar = p.estado !== 'Activo';
    if (!activar && !confirm(`¿Desactivar a ${p.nombre}? No aparecerá al registrar facturas, pero su historial se conserva.`)) return;

    const data = await llamar('/' + p.id + '/estado', 'POST', { activo: activar });
    if (!data.ok) return alert(data.mensaje);
    await cargar();
}

// ==================== EVENTOS ====================
document.addEventListener('click', (e) => {
    const puntos = e.target.closest('.btn-dots');
    if (puntos) {
        e.stopPropagation();
        const menu = document.querySelector(`.dropdown-menu[data-menu-id="${puntos.dataset.id}"]`);
        const estaba = menu.classList.contains('open');
        cerrarMenus();
        if (!estaba) menu.classList.add('open');
        return;
    }
    if (!e.target.closest('.menu-container')) cerrarMenus();

    const accion = e.target.closest('[data-action]');
    if (accion) {
        const p = proveedores.find((x) => x.id === parseInt(accion.dataset.id, 10));
        cerrarMenus();
        if (p && accion.dataset.action === 'editar') abrirFormulario(p);
        if (p && accion.dataset.action === 'estado') cambiarEstado(p);
    }

    if (e.target.closest('[data-close]') || e.target === modal) cerrar();
});

$('btnCreate').addEventListener('click', () => abrirFormulario());

$('btnBack').addEventListener('click', () => {
    window.location.href = window.LEDGERA.base + '/dashboard';
});

if (window.LEDGERA.usuario) $('headerUserName').textContent = window.LEDGERA.usuario.nombre;

cargar();
