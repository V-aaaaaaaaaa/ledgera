// =====================================================
// LEDGERA · Filtros de escritura
// Lo carga el layout en todas las pantallas. Se usa con un atributo en el <input>:
//   data-filtro="numeros"  -> solo dígitos (cédula, teléfono)
//   data-filtro="letras"   -> solo letras (con tildes y ñ) y espacios (nombres de personas)
//   data-filtro="usuario"  -> letras sin tilde, números, punto, guion y guion bajo
//   data-filtro="texto"    -> letras, números, espacios y . , - & # (razón social, puesto, bodega)
// Esto solo ayuda al usuario mientras escribe: el servidor vuelve a validar todo.
// =====================================================
(function () {
  const PERMITIDO = {
    numeros: /[^0-9]/g,
    letras:  /[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ ]/g,
    usuario: /[^A-Za-z0-9._-]/g,
    texto:   /[^A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ .,&#-]/g
  };

  // Se escucha en todo el documento: funciona también con campos que se crean después
  document.addEventListener('input', (e) => {
    const el = e.target;
    const filtro = el.dataset ? el.dataset.filtro : null;
    if (!filtro || !PERMITIDO[filtro]) return;

    let limpio = el.value.replace(PERMITIDO[filtro], '');
    if (filtro === 'letras' || filtro === 'texto') limpio = limpio.replace(/ {2,}/g, ' ').replace(/^ /, '');

    if (limpio !== el.value) {
      const pos = el.selectionStart - (el.value.length - limpio.length);
      el.value = limpio;
      try { el.setSelectionRange(pos, pos); } catch (_) {}
    }
  });
})();
