// =====================================================
// LEDGERA · Ayudante común para hablar con el servidor
// Lo carga el layout en todas las pantallas.
// =====================================================
(function () {
  const base = () => (window.LEDGERA && window.LEDGERA.base) || '';
  const token = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  };

  // Bajo nivel: NO redirige. Devuelve { status, data }. Lo usan el login y el cambio de clave.
  window.ledgeraFetch = async function (ruta, opciones = {}) {
    const r = await fetch(base() + ruta, {
      credentials: 'same-origin',
      ...opciones,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token(),
        ...(opciones.headers || {})
      }
    });
    const data = await r.json().catch(() => ({ ok: false, mensaje: 'El servidor respondió algo inesperado.' }));
    return { status: r.status, data };
  };

  // Para las pantallas con sesión: si la sesión caduca o hay clave temporal, redirige solo.
  window.api = async function (ruta, opciones = {}) {
    const { status, data } = await window.ledgeraFetch(ruta, opciones);
    if (status === 401 || status === 419) { window.location.href = base() + '/'; throw new Error('sin sesion'); }
    if (data && data.cambiar_clave) { window.location.href = base() + '/cambiar-clave'; throw new Error('sin sesion'); }
    return data;
  };
})();
