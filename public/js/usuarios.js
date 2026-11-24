// =====================================================
// LEDGERA · Administrador · Usuarios
// Habla con Admin\UsuarioController (rutas /admin/usuarios...).
// =====================================================
const ES_ADMIN = window.LEDGERA.usuario?.rol === 'Administrador';

const RUTA = ES_ADMIN
    ? '/admin/usuarios'
    : '/gestion/usuarios';

// ==================== ESTADO LOCAL ====================
let usuarios = [];
let puestos = [];

// ==================== ELEMENTOS DOM ====================
const $ = (id) => document.getElementById(id);
const tbody = $('usersTableBody');
const emptyState = $('emptyState');
const createModal = $('createModal');
const editModal = $('editModal');
const claveModal = $('claveModal');
const createForm = $('createUserForm');
const editForm = $('editUserForm');

// ==================== UTILIDADES ====================
function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function showMessage(elementId, message, type) {
    const el = $(elementId);
    if (!el || !message) return;
    el.textContent = message;
    el.style.display = 'block';
    el.className = type === 'error' ? 'error-message' : 'success-message';
    setTimeout(() => { el.style.display = 'none'; }, 4000);
}

const openModal = (m) => m.classList.add('open');
const closeModal = (m) => m.classList.remove('open');
const closeAllDropdowns = () =>
    document.querySelectorAll('.dropdown-menu.open').forEach((m) => m.classList.remove('open'));

const ROL_TEXTO = { Administrador: 'Administrador', Dueno: 'Dueño', Vendedor: 'Vendedor' };

// Llama al servidor. Si no hay internet devuelve un mensaje claro en vez de fallar.
async function llamar(ruta, opciones) {
    try {
        return await api(RUTA + ruta, opciones);
    } catch (e) {
        if (e.message === 'sin sesion') return { ok: false, mensaje: '' };   // api() ya está redirigiendo al login
        return { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }
}

const enviar = (ruta, metodo, cuerpo) =>
    llamar(ruta, { method: metodo, body: cuerpo ? JSON.stringify(cuerpo) : undefined });

function mostrarClave(clave) {
    $('claveGenerada').value = clave;
    $('btnCopiarClave').textContent = 'Copiar';
    openModal(claveModal);
}

// ==================== PUESTOS (selects) ====================
function llenarSelectPuestos(select) {
    select.innerHTML = '<option value="">Elige un puesto</option>' +
        puestos.map((p) => `<option value="${p.id}">${esc(p.nombre)}</option>`).join('');
}

function ajustarPuestoSegunRol(rol, fila, select) {
    const sinPuesto = rol === 'Administrador';
    fila.style.display = sinPuesto ? 'none' : '';
    select.required = !sinPuesto;
    if (sinPuesto) select.value = '';
}

// ==================== RENDER TABLA ====================
function renderTabla() {
    tbody.innerHTML = '';
    if (usuarios.length === 0) { emptyState.style.display = 'block'; return; }
    emptyState.style.display = 'none';

    usuarios.forEach((u) => {
        const activo = u.estado === 'Activo';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                ${esc(u.usuario)}
                <span class="badge ${activo ? 'active' : 'inactive'}">${activo ? 'Activo' : 'Inactivo'}</span>
            </td>
            <td>${esc(u.nombre)}</td>
            <td>${esc(u.cedula)}</td>
            <td>${esc(ROL_TEXTO[u.rol] || u.rol)}</td>
            <td>${esc(u.puesto || '—')}</td>
            <td class="col-actions">
                <div class="menu-container">
                    <button class="btn-dots" data-id="${u.id}" title="Opciones">
                        <i class="fas fa-ellipsis-vertical"></i>
                    </button>
                    <div class="dropdown-menu" data-menu-id="${u.id}">
                        <button class="dropdown-item" data-action="editar" data-id="${u.id}">
                            <i class="fas fa-pen"></i> Editar
                        </button>
                        <button class="dropdown-item" data-action="restablecer" data-id="${u.id}">
                            <i class="fas fa-key"></i> Restablecer clave
                        </button>
                        <button class="dropdown-item ${activo ? 'danger' : ''}" data-action="toggle" data-id="${u.id}">
                            <i class="fas fa-${activo ? 'ban' : 'check'}"></i> ${activo ? 'Desactivar' : 'Activar'}
                        </button>
                    </div>
                </div>
            </td>`;
        tbody.appendChild(tr);
    });
}

// ==================== CARGAR ====================
async function cargarUsuarios() {
    const data = await llamar('/datos');
    if (!data.ok) { if (data.mensaje) alert(data.mensaje); return; }
    usuarios = data.usuarios;
    puestos = data.puestos;
    llenarSelectPuestos($('newPuesto'));
    llenarSelectPuestos($('editPuesto'));
    renderTabla();
}

// ==================== EVENTOS: DELEGACIÓN ====================
document.addEventListener('click', (e) => {
    const dotsBtn = e.target.closest('.btn-dots');
    if (dotsBtn) {
        e.stopPropagation();
        const menu = document.querySelector(`.dropdown-menu[data-menu-id="${dotsBtn.dataset.id}"]`);
        const estabaAbierto = menu.classList.contains('open');
        closeAllDropdowns();
        if (!estabaAbierto) menu.classList.add('open');
        return;
    }
    if (!e.target.closest('.menu-container')) closeAllDropdowns();

    const actionBtn = e.target.closest('[data-action]');
    if (actionBtn) {
        const idNum = parseInt(actionBtn.dataset.id, 10);
        const u = usuarios.find((x) => x.id === idNum);
        closeAllDropdowns();
        if (!u) return;
        if (actionBtn.dataset.action === 'editar') abrirEditar(u);
        if (actionBtn.dataset.action === 'restablecer') restablecerClave(u);
        if (actionBtn.dataset.action === 'toggle') toggleActivo(u);
    }

    const cerrar = e.target.closest('[data-close]');
    if (cerrar) closeModal($(cerrar.dataset.close));
    if (e.target.classList.contains('modal-overlay')) closeModal(e.target);
});

// ==================== CREAR ====================
$('btnCreate').addEventListener('click', () => {
    createForm.reset();
    ajustarPuestoSegunRol($('newRol').value, $('rowNewPuesto'), $('newPuesto'));
    openModal(createModal);
});

$('newRol').addEventListener('change', () =>
    ajustarPuestoSegunRol($('newRol').value, $('rowNewPuesto'), $('newPuesto')));

createForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = await enviar('', 'POST', {
        usuario: $('newUsuario').value.trim(),
        nombre: $('newNombre').value.trim(),
        cedula: $('newCedula').value.trim(),
        rol: $('newRol').value,
        id_puesto: $('newPuesto').value || null
    });
    if (!data.ok) return showMessage('createError', data.mensaje, 'error');

    closeModal(createModal);
    await cargarUsuarios();
    mostrarClave(data.clave);
});

// ==================== EDITAR ====================
function abrirEditar(u) {
    $('editId').value = u.id;
    $('editUsuario').value = u.usuario;
    $('editNombre').value = u.nombre;
    $('editCedula').value = u.cedula;
    ajustarPuestoSegunRol(u.rol, $('rowEditPuesto'), $('editPuesto'));
    $('editPuesto').value = u.id_puesto || '';
    $('editPuesto').disabled = true;
    openModal(editModal);
}

editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = parseInt($('editId').value, 10);
    const data = await enviar('/' + id, 'PUT', {
        usuario: $('editUsuario').value.trim(),
        nombre: $('editNombre').value.trim(),
        cedula: $('editCedula').value.trim(),
        id_puesto: $('editPuesto').value || null
    });
    if (!data.ok) return showMessage('editError', data.mensaje, 'error');

    showMessage('editSuccess', 'Cambios guardados', 'success');
    await cargarUsuarios();
    setTimeout(() => closeModal(editModal), 700);
});

// ==================== RESTABLECER CLAVE ====================
async function restablecerClave(u) {
    if (!confirm(`¿Restablecer la clave de ${u.nombre}?\nSe generará una clave temporal nueva.`)) return;
    const data = await enviar('/' + u.id + '/restablecer-clave', 'POST');
    if (!data.ok) { if (data.mensaje) alert(data.mensaje); return; }
    mostrarClave(data.clave);
}

$('btnCopiarClave').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText($('claveGenerada').value); } catch (_) { $('claveGenerada').select(); }
    $('btnCopiarClave').textContent = 'Copiada';
});

// ==================== ACTIVAR / DESACTIVAR ====================
async function toggleActivo(u) {
    const activo = u.estado === 'Activo';
    if (!confirm(`¿Seguro que quieres ${activo ? 'desactivar' : 'activar'} a ${u.nombre}?`)) return;
    const data = await enviar('/' + u.id + '/estado', 'POST', { activo: !activo });
    if (!data.ok) { if (data.mensaje) alert(data.mensaje); return; }
    await cargarUsuarios();
}

// ==================== HEADER ====================
$('btnBack').addEventListener('click', () => {
    window.location.href = window.LEDGERA.base
        + (ES_ADMIN ? '/admin' : '/dashboard');
});

// ==================== INICIAR ====================
cargarUsuarios();
