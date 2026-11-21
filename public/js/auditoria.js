// =====================================================
// LEDGERA · Administrador · Auditoría
// Habla con Admin\AuditoriaController (ruta /admin/auditoria/datos).
// =====================================================
const RUTA = '/admin/auditoria/datos';

const $ = (id) => document.getElementById(id);
let pagina = 1;
let paginas = 1;
let accionesCargadas = false;

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// "2026-11-21 16:29:05" -> "21/11/2026 16:29"
function formatoFecha(texto) {
    const m = String(texto).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : esc(texto);
}

const ALERTAS = ['CUENTA_BLOQUEADA', 'USUARIO_DESACTIVADO', 'PUESTO_DESACTIVADO', 'CLAVE_RESTABLECIDA'];

function llenarAcciones(acciones) {
    if (accionesCargadas) return;
    const sel = $('fAccion');
    Object.entries(acciones).forEach(([codigo, texto]) => {
        const op = document.createElement('option');
        op.value = codigo;
        op.textContent = texto;
        sel.appendChild(op);
    });
    accionesCargadas = true;
}

async function cargar() {
    const params = new URLSearchParams({
        desde: $('fDesde').value,
        hasta: $('fHasta').value,
        accion: $('fAccion').value,
        q: $('fBuscar').value.trim(),
        pagina: String(pagina)
    });

    let data;
    try {
        data = await api(RUTA + '?' + params.toString());
    } catch (e) {
        if (e.message === 'sin sesion') return;   // api() ya está redirigiendo al login
        alert('No hay conexión con el servidor. Revisa tu internet.');
        return;
    }
    if (!data.ok) { if (data.mensaje) alert(data.mensaje); return; }

    llenarAcciones(data.acciones);
    pagina = data.pagina;
    paginas = data.paginas;

    const tbody = $('auditoriaBody');
    tbody.innerHTML = data.filas.map((f) => `
        <tr>
            <td>${formatoFecha(f.fecha)}</td>
            <td><span class="etiqueta-accion ${ALERTAS.includes(f.accion) ? 'alerta' : ''}">${esc(f.texto)}</span></td>
            <td>${esc(f.actor)}</td>
            <td>${f.afectado ? esc(f.afectado) : '—'}</td>
            <td>${f.puesto ? esc(f.puesto) : '—'}</td>
            <td>${f.detalle ? esc(f.detalle) : '—'}</td>
        </tr>`).join('');

    $('emptyState').style.display = data.filas.length === 0 ? 'block' : 'none';
    $('infoPagina').textContent = `Página ${pagina} de ${paginas} · ${data.total} registros`;
    $('btnAnterior').disabled = pagina <= 1;
    $('btnSiguiente').disabled = pagina >= paginas;
}

$('btnFiltrar').addEventListener('click', () => { pagina = 1; cargar(); });
$('btnLimpiar').addEventListener('click', () => {
    ['fDesde', 'fHasta', 'fAccion', 'fBuscar'].forEach((id) => { $(id).value = ''; });
    pagina = 1;
    cargar();
});
$('fBuscar').addEventListener('keydown', (e) => { if (e.key === 'Enter') { pagina = 1; cargar(); } });
$('btnAnterior').addEventListener('click', () => { if (pagina > 1) { pagina--; cargar(); } });
$('btnSiguiente').addEventListener('click', () => { if (pagina < paginas) { pagina++; cargar(); } });

$('btnBack').addEventListener('click', () => { window.location.href = window.LEDGERA.base + '/admin'; });
if (window.LEDGERA.usuario) $('headerUserName').textContent = window.LEDGERA.usuario.nombre;

cargar();
