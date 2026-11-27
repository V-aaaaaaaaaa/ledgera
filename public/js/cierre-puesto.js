// =====================================================
// LEDGERA · Cierre del puesto (consolidado)
// Habla con CierrePuestoController (rutas /cierre-caja/puesto).
// Se habilita solo cuando todas las cajas del puesto están cerradas.
// =====================================================
(function () {
  const $ = (id) => document.getElementById(id);
  if (!$('cierrePuesto')) return;

  const pesos = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  const escapar = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const dif = (d) => d === 0 ? '✓ Cuadró' : (d > 0 ? `Sobró ${pesos(d)}` : `Faltó ${pesos(-d)}`);

  async function llamar(metodo = 'GET') {
    try {
      return await api('/cierre-caja/puesto', { method: metodo });
    } catch (e) {
      if (e.message === 'sin sesion') return { ok: false, mensaje: '' };
      return { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }
  }

  function error(t) { $('cpuMsg').textContent = t || ''; $('cpuMsg').hidden = !t; }

  async function cargar() {
    const r = await llamar();
    $('cierrePuesto').hidden = false;
    if (!r.ok) return error(r.mensaje);
    error('');

    // Quién falta por cerrar su caja
    $('cpuAbiertas').hidden = r.abiertas.length === 0;
    $('cpuAbiertas').innerHTML = r.abiertas.length
      ? '<i class="fas fa-lock-open"></i> <strong>Falta que cierre su caja:</strong> '
        + r.abiertas.map((a) => `${escapar(a.persona)} <small>(abierta desde ${a.desde})</small>`).join(', ')
      : '';

    // Cajas ya cerradas que entran en el cierre del puesto
    $('cpuLista').innerHTML = r.pendientes.map((c) => `
      <li class="${c.descuadre ? 'cpu__descuadre' : ''}">
        <div><strong>${escapar(c.persona)}</strong><small>Cerró ${c.hora}</small></div>
        <div class="cpu__num">Esperado ${pesos(c.esperado)} · Contado ${pesos(c.contado)}<br><b>${dif(c.diferencia)}</b></div>
      </li>`).join('');

    const t = r.totales;
    $('cpuTotal').hidden = r.pendientes.length === 0;
    $('cpuTotal').innerHTML = `
      <div><span>Efectivo esperado</span><strong>${pesos(t.esperado)}</strong></div>
      <div><span>Efectivo contado</span><strong>${pesos(t.contado)}</strong></div>
      <div><span>Diferencia</span><strong>${dif(t.diferencia)}</strong></div>
      <div><span>Digital · Fiado</span><strong>${pesos(t.digital)} · ${pesos(t.fiado)}</strong></div>`;

    $('btnCierrePuesto').hidden = !r.puede_cerrar;
    if (!r.puede_cerrar && r.pendientes.length === 0 && r.abiertas.length === 0) {
      $('cpuLista').innerHTML = '<li class="cpu__vacio">No hay cajas pendientes por consolidar.</li>';
    }

    $('cpuUltimo').hidden = !r.ultimo;
    if (r.ultimo) {
      const u = r.ultimo;
      $('cpuUltimo').innerHTML = `Último cierre del puesto: <strong>${u.fecha}</strong> por ${escapar(u.persona)} · ${u.cajas} cajas · ${dif(u.diferencia)}`;
    }
  }

  async function cerrarPuesto() {
    if (!confirm('¿Hacer el cierre del puesto?\nSe sumarán todas las cajas cerradas y no se podrá modificar.')) return;
    const btn = $('btnCierrePuesto');
    btn.disabled = true;                  // evita doble cierre por doble toque
    const r = await llamar('POST');
    btn.disabled = false;
    if (!r.ok) { error(r.mensaje); return cargar(); }
    if (typeof avisar === 'function') avisar(r.mensaje);
    cargar();
  }

  $('btnCierrePuesto').addEventListener('click', cerrarPuesto);
  window.recargarCierrePuesto = cargar;
  cargar();
})();
