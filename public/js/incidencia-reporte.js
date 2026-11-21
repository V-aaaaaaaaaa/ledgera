// =====================================================
// LEDGERA · Reportar un problema (Dueño y Vendedor)
// Envía a IncidenciaController (POST /incidencias).
// =====================================================
(function () {
  const modal = document.getElementById('modalReporte');
  if (!modal) return;

  const $ = (id) => document.getElementById(id);
  let impacto = '';
  let enviando = false;

  function mostrar(texto, esOk) {
    const el = $('repMsg');
    el.textContent = texto || '';
    el.className = 'rep-msg' + (esOk ? ' ok' : '');
    el.hidden = !texto;
  }

  function contador() {
    $('repContador').textContent = $('repDescripcion').value.length + ' / 500';
  }

  function limpiar() {
    impacto = '';
    $('repModulo').value = '';
    $('repDescripcion').value = '';
    document.querySelectorAll('.rep-impacto').forEach((b) => b.classList.remove('activo'));
    $('repEnviar').disabled = false;
    mostrar('');
    contador();
  }

  function abrir() {
    limpiar();
    modal.hidden = false;
    setTimeout(() => $('repModulo').focus(), 50);
  }

  const cerrar = () => { modal.hidden = true; };

  document.querySelectorAll('[data-reportar]').forEach((a) =>
    a.addEventListener('click', (e) => { e.preventDefault(); abrir(); }));

  $('reporteCerrar').addEventListener('click', cerrar);
  modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.hidden) cerrar(); });
  $('repDescripcion').addEventListener('input', contador);

  $('repImpactos').addEventListener('click', (e) => {
    const b = e.target.closest('.rep-impacto');
    if (!b) return;
    impacto = b.dataset.impacto;
    document.querySelectorAll('.rep-impacto').forEach((x) => x.classList.toggle('activo', x === b));
  });

  $('reporteForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (enviando) return;

    const modulo = $('repModulo').value;
    const descripcion = $('repDescripcion').value.trim();
    if (!modulo) return mostrar('Elige en qué parte pasó.');
    if (!impacto) return mostrar('Elige qué tan grave es.');
    if (descripcion.length < 10) return mostrar('Cuéntanos un poco más (mínimo 10 letras).');
    if (!navigator.onLine) return mostrar('Sin conexión. No se envió nada. Revisa tu internet e inténtalo de nuevo.');

    enviando = true;                       // evita enviar dos veces con doble toque
    $('repEnviar').disabled = true;
    try {
      const data = await api('/incidencias', {
        method: 'POST',
        body: JSON.stringify({ modulo, impacto, descripcion })
      });
      if (data.ok) {
        mostrar(data.mensaje, true);
        setTimeout(cerrar, 2500);
      } else {
        mostrar(data.mensaje || 'No se pudo enviar el reporte.');
        $('repEnviar').disabled = false;
      }
    } catch (err) {
      if (err.message !== 'sin sesion') {
        mostrar('No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.');
        $('repEnviar').disabled = false;
      }
    } finally {
      enviando = false;
    }
  });

  contador();
})();
