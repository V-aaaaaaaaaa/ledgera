// =====================================================
// LEDGERA · Egresos (RF-014)
// Habla con EgresoController (rutas /egresos...).
// El servidor vuelve a validar todo; aquí solo se ayuda a llenar.
// =====================================================
(function () {
  const $ = (id) => document.getElementById(id);
  const ETIQUETA = { Gasto_urgencia: 'Gasto de urgencia', Otros: 'Otros', Pago_proveedor: 'Pago a proveedor' };

  let datos = null;
  let categoria = 'Gasto_urgencia';
  let medio = 'Efectivo';
  let facturas = [];

  const pesos = (n) => '$' + Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  const numero = (s) => parseInt(String(s).replace(/\D/g, ''), 10) || 0;
  const escapar = (s) => String(s ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function error(texto) { $('msg').textContent = texto || ''; $('msg').hidden = !texto; }

  async function llamar(ruta, metodo = 'GET', cuerpo) {
    try {
      return await api('/egresos' + ruta, { method: metodo, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
    } catch (e) {
      if (e.message === 'sin sesion') return { ok: false, mensaje: '' };
      return { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }
  }

  // Monto con puntos de miles mientras escribe
  $('monto').addEventListener('input', () => {
    const d = $('monto').value.replace(/\D/g, '').slice(0, 9);
    $('monto').value = d ? Number(d).toLocaleString('es-CO') : '';
  });

  // ---------- Cargar ----------
  async function cargar() {
    const r = await llamar('/datos');
    $('cargando').hidden = true;
    if (!r.ok) { $('conCaja').hidden = false; return error(r.mensaje); }

    datos = r;
    if (!r.abierta) { $('sinCaja').hidden = false; $('conCaja').hidden = true; return; }

    $('conCaja').hidden = false;
    $('totEfectivo').textContent = pesos(r.total_efectivo);
    $('totDigital').textContent = pesos(r.total_digital);
    $('optPago').hidden = !r.puede_pagar;        // Dueño o Vendedor encargado
    llenarProveedores();
    pintarLista();
  }

  function pintarLista() {
    const ul = $('lista');
    ul.innerHTML = '';
    $('listaVacia').hidden = datos.egresos.length > 0;

    datos.egresos.forEach((e) => {
      const li = document.createElement('li');
      li.innerHTML = `
        <div class="egr__item-izq">
          <span class="egr__tipo egr__tipo--${e.categoria}">${ETIQUETA[e.categoria]}</span>
          <p>${escapar(e.detalle || '')}</p>
          <small>${e.hora} · ${escapar(e.usuario || '')}${e.soporte ? ' · Ref. ' + escapar(e.soporte) : ''}</small>
        </div>
        <div class="egr__item-der">
          <strong>${pesos(e.monto)}</strong>
          <small>${escapar(e.medio)}</small>
        </div>`;
      ul.appendChild(li);
    });
  }

  // ---------- Categoría y medio ----------
  function elegirCategoria(cat) {
    categoria = cat;
    document.querySelectorAll('#categorias button').forEach((b) => b.classList.toggle('activo', b.dataset.cat === cat));
    const esPago = cat === 'Pago_proveedor';
    $('bloquePago').hidden = !esPago;
    $('etqNota').innerHTML = esPago ? 'Nota <small>(opcional)</small>' : '¿En qué se gastó? <small>(obligatorio)</small>';
    $('nota').placeholder = esPago ? 'Ej: abono acordado por teléfono' : 'Ej: descargue del camión de papa';
    error('');
  }

  $('categorias').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-cat]');
    if (b) elegirCategoria(b.dataset.cat);
  });

  $('medios').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-medio]');
    if (!b) return;
    medio = b.dataset.medio;
    document.querySelectorAll('#medios button').forEach((x) => x.classList.toggle('activo', x === b));
  });

  // ---------- Pago a proveedor ----------
  function llenarProveedores() {
    const sel = $('proveedor');
    const lista = datos.proveedores || [];
    sel.innerHTML = '<option value="">Elige un proveedor</option>' +
      lista.map((p) => `<option value="${p.id}">${escapar(p.nombre)} — debe ${pesos(p.deuda)}</option>`).join('');
    $('sinDeudas').hidden = lista.length > 0;
    sel.hidden = lista.length === 0;
    $('facturas').innerHTML = '';
    $('totalSeleccion').hidden = true;
  }

  $('proveedor').addEventListener('change', async () => {
    $('facturas').innerHTML = '';
    $('totalSeleccion').hidden = true;
    facturas = [];
    const id = $('proveedor').value;
    if (!id) return;

    const r = await llamar(`/proveedor/${id}/facturas`);
    if (!r.ok) return error(r.mensaje);
    facturas = r.facturas;

    // De la más vieja a la más nueva; todas marcadas por defecto
    $('facturas').innerHTML = facturas.map((f) => `
      <label class="egr__factura">
        <input type="checkbox" value="${f.id}" checked>
        <span>
          <strong>${escapar(f.documento)}</strong> · ${f.fecha}
          <small>Valor ${pesos(f.valor)} · Pagado ${pesos(f.pagado)}</small>
        </span>
        <b>${pesos(f.saldo)}</b>
      </label>`).join('');
    actualizarSeleccion(true);
  });

  $('facturas').addEventListener('change', () => actualizarSeleccion(true));

  function seleccionadas() {
    return [...$('facturas').querySelectorAll('input:checked')].map((i) => parseInt(i.value, 10));
  }

  // Al cambiar la selección, el monto se llena con el total (se puede bajar para un pago parcial)
  function actualizarSeleccion(llenarMonto) {
    const ids = seleccionadas();
    const total = facturas.filter((f) => ids.includes(f.id)).reduce((s, f) => s + f.saldo, 0);
    $('totalSeleccion').hidden = ids.length === 0;
    $('totalSeleccion').textContent = `Total de lo seleccionado: ${pesos(total)}. Puedes pagar menos (abono parcial).`;
    if (llenarMonto) $('monto').value = total ? Number(total).toLocaleString('es-CO') : '';
  }

  // ---------- Guardar ----------
  async function guardar() {
    error('');
    const monto = numero($('monto').value);
    const nota = $('nota').value.trim();
    const esPago = categoria === 'Pago_proveedor';

    if (monto <= 0) return error('Escribe el monto (mayor que cero).');
    if (!esPago && !nota) { $('nota').focus(); return error('Escribe en qué se gastó.'); }

    const cuerpo = { categoria, monto, medio, nota: nota || null, soporte: $('soporte').value.trim() || null };

    if (esPago) {
      const ids = seleccionadas();
      if (!$('proveedor').value) return error('Elige el proveedor.');
      if (ids.length === 0) return error('Marca al menos una factura.');
      const total = facturas.filter((f) => ids.includes(f.id)).reduce((s, f) => s + f.saldo, 0);
      if (monto > total) return error(`El monto supera lo que se debe en esas facturas (${pesos(total)}).`);
      cuerpo.id_proveedor = parseInt($('proveedor').value, 10);
      cuerpo.facturas = ids;
    }

    const btn = $('btnGuardar');
    btn.disabled = true;                 // evita registrar dos veces por doble toque
    const r = await llamar('', 'POST', cuerpo);
    btn.disabled = false;

    if (!r.ok) return error(r.mensaje || 'No se pudo registrar.');

    // Limpia el formulario y recarga totales y lista
    $('monto').value = '';
    $('nota').value = '';
    $('soporte').value = '';
    elegirCategoria(categoria === 'Pago_proveedor' ? 'Gasto_urgencia' : categoria);
    if (typeof avisar === 'function') avisar(r.mensaje);
    cargar();
  }

  $('btnGuardar').addEventListener('click', guardar);

  const volver = () => { window.location.href = window.LEDGERA.base + '/dashboard'; };
  $('btnBack').addEventListener('click', volver);
  document.querySelectorAll('[data-volver]').forEach((b) => b.addEventListener('click', volver));
  if (window.LEDGERA.usuario) $('headerUserName').textContent = window.LEDGERA.usuario.nombre;

  // El soporte acepta letras, números y . , # / -
  $('soporte').addEventListener('input', () => {
    $('soporte').value = $('soporte').value.replace(/[^A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ .,#\/-]/g, '');
  });

  cargar();
})();
