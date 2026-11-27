// =====================================================
// LEDGERA · Actividad del negocio (solo Dueño)
// Habla con ActividadNegocioController (rutas /negocio/actividad...).
// Solo consulta: no modifica nada.
// =====================================================
(function () {
  const $ = (id) => document.getElementById(id);
  const ICONO = {
    'Venta': 'fa-cart-shopping', 'Venta anulada': 'fa-ban', 'Abono': 'fa-hand-holding-dollar',
    'Egreso': 'fa-money-bill-transfer', 'Entrada de mercancía': 'fa-truck-ramp-box',
    'Ajuste de inventario': 'fa-boxes-stacked', 'Apertura de caja': 'fa-lock-open',
    'Cierre de caja': 'fa-cash-register', 'Cambio': 'fa-pen-to-square', 'Ingreso': 'fa-right-to-bracket'
  };

  let pagina = 1;
  let equipoCargado = false;
  let temporizador = null;

  const pesos = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  const escapar = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const clase = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, '-');

  // Fecha local en formato AAAA-MM-DD
  function fecha(diasAtras) {
    const d = new Date();
    d.setDate(d.getDate() - diasAtras);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  }

  function filtros() {
    const p = new URLSearchParams();
    p.set('desde', $('desde').value);
    p.set('hasta', $('hasta').value);
    if ($('usuario').value) p.set('usuario', $('usuario').value);
    if ($('buscar').value.trim()) p.set('buscar', $('buscar').value.trim());
    document.querySelectorAll('#tipos button.activo').forEach((b) => p.append('tipos[]', b.dataset.tipo));
    p.set('pagina', pagina);
    return p.toString();
  }

  async function cargar(reiniciar = true) {
    if (reiniciar) pagina = 1;
    $('msg').hidden = true;

    let r;
    try {
      r = await api('/negocio/actividad/datos?' + filtros());
    } catch (e) {
      if (e.message === 'sin sesion') return;
      r = { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }

    if (!r.ok) { $('msg').textContent = r.mensaje; $('msg').hidden = false; return; }

    if (!equipoCargado) {
      $('usuario').innerHTML = '<option value="">Todos</option>' + r.equipo.map((u) =>
        `<option value="${u.id}">${escapar(u.nombre)}${u.rol === 'Dueno' ? ' (tú)' : ''}</option>`).join('');
      equipoCargado = true;
    }

    if (reiniciar) { $('lista').innerHTML = ''; pintarResumen(r.resumen); }
    pintarFilas(r.filas);
    $('vacio').hidden = $('lista').children.length > 0;
    $('btnMas').hidden = !r.hay_mas;
  }

  function pintarResumen(resumen) {
    $('resumen').innerHTML = resumen.map((r) => `
      <div class="act__dato act__dato--${clase(r.tipo)}">
        <span>${escapar(r.tipo)} <small>(${r.cantidad})</small></span>
        <strong>${r.total !== null ? pesos(r.total) : r.cantidad}</strong>
        ${r.alertas ? `<em><i class="fas fa-triangle-exclamation"></i> ${r.alertas} para revisar</em>` : ''}
      </div>`).join('');
  }

  function pintarFilas(filas) {
    let ultimaFecha = $('lista').dataset.ultimaFecha || '';
    if (pagina === 1) ultimaFecha = '';

    filas.forEach((f) => {
      // Separador por día
      if (f.fecha !== ultimaFecha) {
        const sep = document.createElement('li');
        sep.className = 'act__dia';
        sep.textContent = f.fecha === fecha(0) ? 'Hoy' : f.fecha;
        $('lista').appendChild(sep);
        ultimaFecha = f.fecha;
      }

      const li = document.createElement('li');
      li.className = 'act__item' + (f.alerta ? ' act__item--alerta' : '');
      li.innerHTML = `
        <i class="fas ${ICONO[f.tipo] || 'fa-circle'} act__icono act__icono--${clase(f.tipo)}"></i>
        <div class="act__cuerpo">
          <p><strong>${escapar(f.tipo)}</strong>${f.alerta ? ' <span class="act__alerta">Revisar</span>' : ''}</p>
          <p class="act__detalle">${escapar(f.detalle)}</p>
          <small>${f.hora} · ${escapar(f.quien)}</small>
        </div>
        <div class="act__valor">
          ${f.monto !== null ? `<strong>${pesos(f.monto)}</strong>` : ''}
          ${f.medio ? `<small>${escapar(f.medio)}</small>` : ''}
        </div>`;
      $('lista').appendChild(li);
    });
    $('lista').dataset.ultimaFecha = ultimaFecha;
  }

  // ---------- Eventos ----------
  $('atajos').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-dias]');
    if (!b) return;
    document.querySelectorAll('#atajos button').forEach((x) => x.classList.toggle('activo', x === b));
    const dias = parseInt(b.dataset.dias, 10);
    $('desde').value = fecha(dias);
    $('hasta').value = dias === 1 ? fecha(1) : fecha(0);
    cargar();
  });

  ['desde', 'hasta', 'usuario'].forEach((id) => $(id).addEventListener('change', () => {
    document.querySelectorAll('#atajos button').forEach((x) => x.classList.remove('activo'));
    cargar();
  }));

  $('tipos').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-tipo]');
    if (!b) return;
    b.classList.toggle('activo');
    cargar();
  });

  // Busca medio segundo después de dejar de escribir (no una consulta por cada letra)
  $('buscar').addEventListener('input', () => {
    clearTimeout(temporizador);
    temporizador = setTimeout(() => cargar(), 500);
  });

  $('btnLimpiar').addEventListener('click', () => {
    $('buscar').value = '';
    $('usuario').value = '';
    document.querySelectorAll('#tipos button').forEach((b) => b.classList.remove('activo'));
    document.querySelector('#atajos button[data-dias="0"]').click();
  });

  $('btnMas').addEventListener('click', () => { pagina++; cargar(false); });

  $('btnBack').addEventListener('click', () => { window.location.href = window.LEDGERA.base + '/dashboard'; });
  if (window.LEDGERA.usuario) $('headerUserName').textContent = window.LEDGERA.usuario.nombre;

  $('desde').value = fecha(0);
  $('hasta').value = fecha(0);
  cargar();
})();
