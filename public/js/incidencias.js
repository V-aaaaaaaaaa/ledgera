// =====================================================
// LEDGERA · Administrador · Pestañas y pestaña "Incidencias"
// Habla con Admin\IncidenciaController (rutas /admin/incidencias...).
// Va dentro de una función para no chocar con auditoria.js.
// =====================================================
(function () {
  const RUTA = '/admin/incidencias';
  const $ = (id) => document.getElementById(id);
  const ESTADOS = ['Abierta', 'En proceso', 'Resuelta'];
  const IMPACTOS = ['Alto', 'Medio', 'Bajo'];
  const ROL_TEXTO = { Dueno: 'Dueño', Vendedor: 'Vendedor', Administrador: 'Administrador' };

  let pagina = 1;
  let paginas = 1;

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function formatoFecha(texto) {
    const m = String(texto).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : esc(texto);
  }

  const opciones = (lista, actual) =>
    lista.map((v) => `<option value="${v}" ${v === actual ? 'selected' : ''}>${v}</option>`).join('');

  function mensaje(texto, esError) {
    const el = $('iMensaje');
    el.textContent = texto || '';
    el.className = 'inc-mensaje' + (esError ? ' error' : '');
    el.hidden = !texto;
  }

  function tarjeta(i) {
    const claseEstado = i.estado === 'En proceso' ? 'En' : i.estado;
    return `
      <article class="inc ${i.impacto.toLowerCase()}" data-id="${i.id}">
        <div class="inc-cab">
          <span class="inc-num">#${i.id}</span>
          <span class="inc-badges">
            <span class="b-inc i-${i.impacto}">Impacto ${i.impacto}</span>
            <span class="b-inc e-${claseEstado}">${i.estado}</span>
          </span>
        </div>
        <p class="inc-meta">
          <i class="fas fa-user"></i> ${esc(i.persona)}${i.rol ? ' · ' + esc(ROL_TEXTO[i.rol] || i.rol) : ''}<br>
          <i class="fas fa-store"></i> ${esc(i.puesto || 'Sin puesto')}<br>
          <i class="fas fa-layer-group"></i> ${esc(i.modulo)}<br>
          <i class="fas fa-clock"></i> ${formatoFecha(i.fecha)}
        </p>
        <p class="inc-desc">${esc(i.descripcion)}</p>
        <div class="inc-acciones">
          <label>Estado <select class="sel-estado">${opciones(ESTADOS, i.estado)}</select></label>
          <label>Impacto <select class="sel-impacto">${opciones(IMPACTOS, i.impacto)}</select></label>
          <button type="button" class="btn-filtro btn-guardar-inc"><i class="fas fa-floppy-disk"></i> Guardar</button>
        </div>
      </article>`;
  }

  function pintarResumen(c) {
    const pendientes = c['Abierta'] + c['En proceso'];
    $('resumenInc').innerHTML = `
      <div class="dato"><strong>${c['Abierta']}</strong>Abiertas</div>
      <div class="dato"><strong>${c['En proceso']}</strong>En proceso</div>
      <div class="dato"><strong>${c['Resuelta']}</strong>Resueltas</div>`;
    const contador = $('contadorInc');
    contador.textContent = pendientes;
    contador.hidden = pendientes === 0;
  }

  async function cargar() {
    const params = new URLSearchParams({
      estado: $('iEstado').value,
      impacto: $('iImpacto').value,
      pagina: String(pagina)
    });

    let data;
    try {
      data = await api(RUTA + '/datos?' + params.toString());
    } catch (e) {
      if (e.message === 'sin sesion') return;   // api() ya está redirigiendo al login
      mensaje('No hay conexión con el servidor. Revisa tu internet.', true);
      return;
    }
    if (!data.ok) { mensaje(data.mensaje || 'No se pudo cargar.', true); return; }

    mensaje('');
    pagina = data.pagina;
    paginas = data.paginas;
    pintarResumen(data.conteos);

    $('listaIncidencias').innerHTML = data.filas.map(tarjeta).join('');
    $('iVacio').style.display = data.filas.length === 0 ? 'block' : 'none';
    $('iInfoPagina').textContent = `Página ${pagina} de ${paginas} · ${data.total} incidencias`;
    $('iAnterior').disabled = pagina <= 1;
    $('iSiguiente').disabled = pagina >= paginas;
  }

  async function guardar(boton) {
    const tarjetaEl = boton.closest('.inc');
    const id = tarjetaEl.dataset.id;
    boton.disabled = true;
    try {
      const data = await api(RUTA + '/' + id, {
        method: 'POST',
        body: JSON.stringify({
          estado: tarjetaEl.querySelector('.sel-estado').value,
          impacto: tarjetaEl.querySelector('.sel-impacto').value
        })
      });
      if (!data.ok) { mensaje(data.mensaje || 'No se pudo guardar.', true); return; }
      mensaje(`Incidencia #${id} actualizada.`, false);
      await cargar();
    } catch (e) {
      if (e.message !== 'sin sesion') mensaje('No hay conexión con el servidor. Revisa tu internet.', true);
    } finally {
      boton.disabled = false;
    }
  }

  // ---------- Pestañas ----------
  function irATab(nombre) {
    document.querySelectorAll('.tab').forEach((t) => t.classList.toggle('activo', t.dataset.tab === nombre));
    $('tab-acciones').hidden = nombre !== 'acciones';
    $('tab-incidencias').hidden = nombre !== 'incidencias';
    if (nombre === 'incidencias') cargar();
  }

  $('tabs').addEventListener('click', (e) => {
    const t = e.target.closest('.tab');
    if (t) irATab(t.dataset.tab);
  });

  $('iEstado').addEventListener('change', () => { pagina = 1; cargar(); });
  $('iImpacto').addEventListener('change', () => { pagina = 1; cargar(); });
  $('iAnterior').addEventListener('click', () => { if (pagina > 1) { pagina--; cargar(); } });
  $('iSiguiente').addEventListener('click', () => { if (pagina < paginas) { pagina++; cargar(); } });
  $('listaIncidencias').addEventListener('click', (e) => {
    const b = e.target.closest('.btn-guardar-inc');
    if (b) guardar(b);
  });

  // Carga inicial: así el contador de pendientes aparece en la pestaña desde el principio
  cargar();
})();
