// =====================================================
// LEDGERA · Configuración inicial del puesto (Dueño)
// Habla con ConfiguracionInicialController (rutas /configuracion-inicial...).
// En LEDGERA "calidad" = variedad del producto (Tomate -> Chonto).
// =====================================================
(function () {
  const $ = (id) => document.getElementById(id);
  const lista = $('lista');
  const plantilla = $('tplProducto');

  function escapar(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function error(texto) {
    $('msg').textContent = texto || '';
    $('msg').hidden = !texto;
  }

  // ---------- Productos ----------
  function agregarProducto(enfocar = true) {
    const nodo = plantilla.content.firstElementChild.cloneNode(true);
    lista.appendChild(nodo);
    actualizarQuitar();
    if (enfocar) nodo.querySelector('.cfg__nombre').focus();
  }

  // Con un solo producto no se muestra la papelera
  function actualizarQuitar() {
    const productos = lista.querySelectorAll('.cfg__producto');
    productos.forEach((p) => { p.querySelector('.cfg__quitar').hidden = productos.length === 1; });
  }

  // ---------- Calidades (chips) ----------
  function agregarCalidad(producto) {
    const input = producto.querySelector('.cfg__calidad');
    const valor = input.value.trim().replace(/\s+/g, ' ');
    if (!valor) return;

    const chips = producto.querySelector('.cfg__chips');
    const repetida = [...chips.querySelectorAll('.cfg__chip')]
      .some((c) => c.dataset.valor.toLowerCase() === valor.toLowerCase());

    if (!repetida) {
      const chip = document.createElement('span');
      chip.className = 'cfg__chip';
      chip.dataset.valor = valor;
      chip.innerHTML = `${escapar(valor)} <button type="button" aria-label="Quitar ${escapar(valor)}">&times;</button>`;
      chips.appendChild(chip);
    }
    input.value = '';
    input.focus();
  }

  // ---------- Leer lo escrito ----------
  function recoger() {
    const productos = [];
    lista.querySelectorAll('.cfg__producto').forEach((p) => {
      agregarCalidad(p);   // si dejó una calidad escrita sin presionar +, también cuenta
      const nombre = p.querySelector('.cfg__nombre').value.trim();
      const calidades = [...p.querySelectorAll('.cfg__chip')].map((c) => c.dataset.valor);
      if (nombre || calidades.length) productos.push({ nombre, calidades });
    });
    return productos;
  }

  async function llamar(ruta, cuerpo) {
    try {
      return await api('/configuracion-inicial' + ruta, { method: 'POST', body: JSON.stringify(cuerpo || {}) });
    } catch (e) {
      if (e.message === 'sin sesion') return { ok: false, mensaje: '' };
      return { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }
  }

  // ---------- Guardar / omitir ----------
  async function guardar() {
    error('');
    const productos = recoger();

    if (productos.length === 0) return error('Agrega al menos un producto.');
    const sinNombre = productos.findIndex((p) => !p.nombre);
    if (sinNombre >= 0) return error(`Escribe el nombre del producto #${sinNombre + 1}.`);

    const btn = $('btnGuardar');
    btn.disabled = true;                 // evita guardar dos veces por doble toque
    btn.textContent = 'Guardando…';
    const r = await llamar('', { productos });
    btn.disabled = false;
    btn.textContent = 'Guardar y empezar';

    if (!r.ok) return error(r.mensaje || 'No se pudo guardar.');
    window.location.href = r.destino;
  }

  async function omitir() {
    const r = await llamar('/omitir');
    window.location.href = r.ok ? r.destino : window.LEDGERA.base + '/dashboard';
  }

  // ---------- Eventos ----------
  lista.addEventListener('click', (e) => {
    const producto = e.target.closest('.cfg__producto');
    if (!producto) return;

    if (e.target.closest('.cfg__mas')) agregarCalidad(producto);
    if (e.target.closest('.cfg__chip button')) e.target.closest('.cfg__chip').remove();
    if (e.target.closest('.cfg__quitar')) { producto.remove(); actualizarQuitar(); }
  });

  lista.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const producto = e.target.closest('.cfg__producto');
    if (e.target.classList.contains('cfg__calidad')) agregarCalidad(producto);
    if (e.target.classList.contains('cfg__nombre')) producto.querySelector('.cfg__calidad').focus();
  });

  $('btnAgregar').addEventListener('click', () => agregarProducto());
  $('btnGuardar').addEventListener('click', guardar);
  $('btnOmitir').addEventListener('click', omitir);

  agregarProducto(false);
})();
