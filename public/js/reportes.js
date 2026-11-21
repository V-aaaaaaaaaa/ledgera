// =====================================================
// LEDGERA · Administrador · Reportes e indicadores del sistema
// Habla con Admin\ReporteController (ruta /admin/reportes/datos).
// =====================================================
const RUTA = '/admin/reportes/datos';
const $ = (id) => document.getElementById(id);

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function formatoFecha(texto) {
    const m = String(texto).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : esc(texto);
}

// Busca "grupo.campo" dentro de los datos (ej: "puestos.activos")
const valorDe = (datos, ruta) => ruta.split('.').reduce((o, k) => (o == null ? o : o[k]), datos);

function pintarIndicadores(datos) {
    document.querySelectorAll('[data-ind]').forEach((el) => {
        const v = valorDe(datos, el.dataset.ind);
        el.textContent = v == null ? '-' : v;
        // Colorea la tarjeta si hay algo que revisar
        const tarjeta = el.closest('.indicador');
        const alerta = tarjeta && tarjeta.dataset.alerta;
        if (alerta) tarjeta.classList.toggle('alerta-' + alerta, Number(v) > 0);
    });
}

function pintarBarras(serie) {
    const maximo = Math.max(1, ...serie.map((d) => d.total));
    $('barras').innerHTML = serie.map((d) => `
        <div class="columna">
            <span class="valor">${d.total}</span>
            <div class="barra" style="height:${Math.round((d.total / maximo) * 100)}%"></div>
            <span class="dia">${esc(d.dia)}</span>
        </div>`).join('');
}

function pintarMovimientos(filas) {
    $('movimientosBody').innerHTML = filas.map((f) => `
        <tr>
            <td>${formatoFecha(f.fecha)}</td>
            <td>${esc(f.texto)}</td>
            <td>${esc(f.actor)}</td>
            <td>${f.afectado ? esc(f.afectado) : '—'}</td>
        </tr>`).join('');
    $('emptyState').style.display = filas.length === 0 ? 'block' : 'none';
}

async function cargar() {
    let data;
    try {
        data = await api(RUTA);
    } catch (e) {
        if (e.message === 'sin sesion') return;   // api() ya está redirigiendo al login
        alert('No hay conexión con el servidor. Revisa tu internet.');
        return;
    }
    if (!data.ok) { if (data.mensaje) alert(data.mensaje); return; }

    pintarIndicadores(data);
    $('generado').textContent = 'Actualizado: ' + data.generado;

    const hayActividad = data.actividad !== null;
    $('sinAuditoria').style.display = hayActividad ? 'none' : 'block';
    $('bloqueActividad').style.display = hayActividad ? 'block' : 'none';
    if (hayActividad) {
        pintarBarras(data.actividad.serie);
        pintarMovimientos(data.ultimos);
    }
}

$('btnActualizar').addEventListener('click', cargar);
$('btnBack').addEventListener('click', () => { window.location.href = window.LEDGERA.base + '/admin'; });
if (window.LEDGERA.usuario) $('headerUserName').textContent = window.LEDGERA.usuario.nombre;

cargar();
