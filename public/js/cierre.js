// =====================================================
// LEDGERA · Cierre de caja (RF-015)
// Habla con CierreCajaController (rutas /cierre-caja...).
// El servidor recalcula todo: aquí solo se muestra y se ayuda a escribir.
// =====================================================
(function () {
  const $ = (id) => document.getElementById(id);
  let datos = null;

  const pesos = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  const numero = (s) => parseInt(String(s).replace(/\D/g, ''), 10) || 0;
  const mostrar = (id, si) => { $(id).hidden = !si; };

  // Puntos de miles mientras escribe (solo números)
  function formatoMonto(input) {
    input.addEventListener('input', () => {
      const d = input.value.replace(/\D/g, '').slice(0, 9);
      input.value = d ? Number(d).toLocaleString('es-CO') : '';
    });
  }

  async function llamar(ruta, metodo = 'GET', cuerpo) {
    try {
      return await api('/cierre-caja' + ruta, { method: metodo, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
    } catch (e) {
      if (e.message === 'sin sesion') return { ok: false, mensaje: '' };
      return { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }
  }

  function error(id, texto) {
    $(id).textContent = texto;
    $(id).hidden = !texto;
  }

  // ---------- Cargar y pintar ----------
  async function cargar() {
    const r = await llamar('/resumen');
    mostrar('cargando', false);
    if (!r.ok) { error('msg', r.mensaje); mostrar('conCaja', true); return; }

    datos = r;
    if (!r.abierta) { mostrar('sinCaja', true); mostrar('conCaja', false); return; }

    const c = r.cuentas;
    const j = r.jornada;
    $('infoJornada').textContent = `Jornada abierta el ${j.fecha} a las ${j.hora}` + (j.abierta_por ? ` por ${j.abierta_por}` : '');
    $('cInicial').textContent = pesos(c.efectivo_inicial);
    $('cVentas').textContent = pesos(c.ventas_efectivo);
    $('cAbonos').textContent = pesos(c.abonos_efectivo);
    $('cReversiones').textContent = pesos(c.reversiones_efectivo);
    mostrar('filaReversiones', c.reversiones_efectivo > 0);
    $('cEgresos').textContent = pesos(c.egresos_efectivo);
    $('cEsperado').textContent = pesos(c.efectivo_esperado);

    $('dVentas').textContent = pesos(c.ventas_digital);
    $('dAbonos').textContent = pesos(c.abonos_digital);
    $('dEgresos').textContent = pesos(c.egresos_digital);
    $('dCredito').textContent = pesos(c.ventas_credito);

    $('umbral').textContent = pesos(r.umbral);
    mostrar('btnUmbral', r.es_dueno);
    mostrar('conCaja', true);
    revisarDiferencia();
  }

  // ---------- Diferencia en vivo ----------
  function revisarDiferencia() {
    if (!datos || !datos.abierta) return;
    const escrito = $('contado').value.trim() !== '';
    const dif = numero($('contado').value) - datos.cuentas.efectivo_esperado;
    const el = $('diferencia');

    if (!escrito) { el.hidden = true; mostrar('bloqueExplicacion', false); return; }

    el.hidden = false;
    el.className = 'cierre__dif';
    if (dif === 0) { el.textContent = '✓ La caja cuadra'; el.classList.add('ok'); }
    else if (dif > 0) { el.textContent = `Sobran ${pesos(dif)}`; el.classList.add('aviso'); }
    else { el.textContent = `Faltan ${pesos(-dif)}`; el.classList.add('aviso'); }

    const pasa = Math.abs(dif) > datos.umbral;
    if (pasa) el.classList.add('mal');
    mostrar('bloqueExplicacion', pasa);
  }

  // ---------- Cerrar ----------
  function pedirConfirmacion() {
    error('msg', '');
    if ($('contado').value.trim() === '') return error('msg', 'Escribe cuánto efectivo contaste (puede ser 0).');

    const dif = numero($('contado').value) - datos.cuentas.efectivo_esperado;
    if (Math.abs(dif) > datos.umbral && !$('explicacion').value.trim()) {
      $('explicacion').focus();
      return error('msg', 'La diferencia supera el límite. Escribe qué pasó.');
    }

    $('textoConfirmar').textContent =
      `Esperado ${pesos(datos.cuentas.efectivo_esperado)} · Contado ${pesos(numero($('contado').value))}`;
    mostrar('modalConfirmar', true);
  }

  async function cerrar() {
    $('siConfirmar').disabled = true;     // evita doble cierre por doble toque
    const r = await llamar('', 'POST', {
      efectivo_contado: numero($('contado').value),
      esperado_visto: datos.cuentas.efectivo_esperado,
      explicacion: $('explicacion').value.trim() || null
    });
    $('siConfirmar').disabled = false;
    mostrar('modalConfirmar', false);

    if (!r.ok) {
      error('msg', r.mensaje || 'No se pudo cerrar.');
      if (r.cambio) cargar();             // hubo movimientos nuevos: se refrescan las cuentas
      return;
    }

    const c = r.cierre;
    const dif = c.diferencia === 0 ? 'La caja cuadró.'
      : c.diferencia > 0 ? `Sobraron ${pesos(c.diferencia)}.` : `Faltaron ${pesos(-c.diferencia)}.`;
    $('resultado').textContent = `Esperado ${pesos(c.esperado)} · Contado ${pesos(c.contado)}. ${dif}`;
    mostrar('conCaja', false);
    mostrar('listo', true);
    // Al cerrar mi caja, puede que ya se pueda hacer el cierre del puesto
    if (typeof window.recargarCierrePuesto === 'function') window.recargarCierrePuesto();
  }

  // ---------- Límite (solo Dueño) ----------
  function abrirUmbral() {
    $('nuevoUmbral').value = Number(datos.umbral).toLocaleString('es-CO');
    error('msgUmbral', '');
    mostrar('modalUmbral', true);
    $('nuevoUmbral').focus();
  }

  async function guardarUmbral() {
    if ($('nuevoUmbral').value.trim() === '') return error('msgUmbral', 'Escribe el límite (puede ser 0).');
    $('guardarUmbral').disabled = true;
    const r = await llamar('/umbral', 'PUT', { umbral: numero($('nuevoUmbral').value) });
    $('guardarUmbral').disabled = false;
    if (!r.ok) return error('msgUmbral', r.mensaje);
    mostrar('modalUmbral', false);
    cargar();
  }

  // ---------- Eventos ----------
  formatoMonto($('contado'));
  formatoMonto($('nuevoUmbral'));
  $('contado').addEventListener('input', revisarDiferencia);
  $('btnCerrar').addEventListener('click', pedirConfirmacion);
  $('siConfirmar').addEventListener('click', cerrar);
  $('noConfirmar').addEventListener('click', () => mostrar('modalConfirmar', false));
  $('btnUmbral').addEventListener('click', abrirUmbral);
  $('guardarUmbral').addEventListener('click', guardarUmbral);
  $('cancelarUmbral').addEventListener('click', () => mostrar('modalUmbral', false));

  const volver = () => { window.location.href = window.LEDGERA.base + '/dashboard'; };
  $('btnBack').addEventListener('click', volver);
  document.querySelectorAll('[data-volver]').forEach((b) => b.addEventListener('click', volver));

  if (window.LEDGERA.usuario) $('headerUserName').textContent = window.LEDGERA.usuario.nombre;

  cargar();
})();
