// =====================================================
// LEDGERA · Apertura de caja (RF-020) en el dashboard
// Un solo botón: "Abrir caja" si está cerrada, "Cierre de caja" si está abierta.
// Habla con CajaController (rutas /caja/...).
// =====================================================
(function () {
  const $ = (id) => document.getElementById(id);
  const btn = $('btnCierre');
  const texto = $('btnCajaTexto');
  const estadoEl = $('cajaEstado');
  const modal = $('modalCaja');
  const input = $('cajaMonto');
  const msg = $('cajaMsg');
  const confirmar = $('cajaConfirmar');

  let caja = { abierta: false, jornada: null, sugerido: null };
  let modo = 'abrir';            // 'abrir' o 'corregir'

  // ---------- Formato de pesos: 150000 -> 150.000 ----------
  const pesos = (n) => Number(n || 0).toLocaleString('es-CO', { maximumFractionDigits: 0 });
  const soloNumero = (s) => parseInt(String(s).replace(/\D/g, ''), 10) || 0;

  // Mientras escribe, se ponen los puntos de miles (más fácil de leer a las 3 a. m.)
  input.addEventListener('input', () => {
    const digitos = input.value.replace(/\D/g, '').slice(0, 9);
    input.value = digitos ? pesos(digitos) : '';
  });

  async function llamar(ruta, metodo = 'GET', cuerpo) {
    try {
      return await api('/caja' + ruta, { method: metodo, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
    } catch (e) {
      if (e.message === 'sin sesion') return { ok: false, mensaje: '' };
      return { ok: false, mensaje: 'No hay conexión con el servidor. Revisa tu internet.' };
    }
  }

  // ---------- Pintar estado ----------
  function pintar() {
    if (caja.abierta) {
      const j = caja.jornada;
      texto.textContent = 'Cierre de caja';
      estadoEl.innerHTML =
        `<span class="caja-estado__punto"></span>
         Caja abierta desde las <strong>${j.hora}</strong>
         ${j.abierta_por ? 'por <strong>' + escapar(j.abierta_por) + '</strong>' : ''}
         · Base: <strong>$${pesos(j.efectivo_inicial)}</strong>
         <button type="button" class="caja-estado__corregir" id="btnCorregirBase">Corregir base</button>`;
      estadoEl.classList.remove('cerrada');
      $('btnCorregirBase').addEventListener('click', () => abrirVentana('corregir'));
    } else {
      texto.textContent = 'Abrir caja';
      estadoEl.innerHTML = '<span class="caja-estado__punto"></span> La caja está cerrada. Ábrela para empezar a vender.';
      estadoEl.classList.add('cerrada');
    }
    estadoEl.hidden = false;
    btn.disabled = false;
  }

  function escapar(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  async function cargar() {
    btn.disabled = true;
    const data = await llamar('/estado');
    if (!data.ok) {
      texto.textContent = 'Caja no disponible';
      if (data.mensaje) {
        estadoEl.textContent = data.mensaje;
        estadoEl.hidden = false;
      }
      return;
    }
    caja = data;
    pintar();
  }

  // ---------- Ventana ----------
  function abrirVentana(nuevoModo) {
    modo = nuevoModo;
    msg.hidden = true;

    if (modo === 'abrir') {
      $('cajaTitulo').textContent = 'Abrir caja';
      $('cajaAyuda').textContent = '¿Con cuánto efectivo empiezas?';
      const s = caja.sugerido;
      input.value = s !== null && s !== undefined ? pesos(s) : '';
      $('cajaNota').textContent = s !== null && s !== undefined
        ? 'Es lo que se contó en el último cierre. Cámbialo si no coincide.'
        : 'Escribe la base para dar vueltas (puede ser 0).';
    } else {
      $('cajaTitulo').textContent = 'Corregir base';
      $('cajaAyuda').textContent = '¿Cuál es el efectivo inicial correcto?';
      input.value = pesos(caja.jornada.efectivo_inicial);
      $('cajaNota').textContent = 'El cambio queda registrado con tu nombre y la hora.';
    }

    modal.hidden = false;
    input.focus();
    input.select();
  }

  const cerrarVentana = () => { modal.hidden = true; };

  async function enviar() {
    if (input.value.trim() === '') {
      msg.textContent = 'Escribe el monto (puede ser 0).';
      msg.hidden = false;
      return;
    }

    confirmar.disabled = true;     // evita doble apertura por doble toque
    const data = await llamar(
      modo === 'abrir' ? '/abrir' : '/apertura',
      modo === 'abrir' ? 'POST' : 'PUT',
      { efectivo_inicial: soloNumero(input.value) }
    );
    confirmar.disabled = false;

    if (!data.ok) {
      msg.textContent = data.mensaje || 'No se pudo guardar.';
      msg.hidden = false;
      if (modo === 'abrir') cargar();  // si otro ya la abrió, refresca el estado
      return;
    }

    cerrarVentana();
    if (typeof avisar === 'function') avisar(data.mensaje);
    cargar();
  }

  // ---------- Eventos ----------
  btn.addEventListener('click', () => {
    if (caja.abierta) irAModulo('cierre');
    else abrirVentana('abrir');
  });
  confirmar.addEventListener('click', enviar);
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') enviar(); });
  $('cajaCancelar').addEventListener('click', cerrarVentana);
  modal.addEventListener('click', (e) => { if (e.target === modal) cerrarVentana(); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarVentana(); });

  document.addEventListener('DOMContentLoaded', cargar);
})();
