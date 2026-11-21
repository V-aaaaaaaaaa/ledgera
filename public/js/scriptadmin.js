// =====================================================
// LEDGERA · Panel de Administración
// =====================================================

// -----------------------------------------------------
// SESIÓN DEL ADMIN
// -----------------------------------------------------
// Se llenará desde login / backend / localStorage.
// Ejemplo cuando ya tengas login:
//   sesion = { nombre: "Carlos Ruiz", iniciales: "CR", rol: "admin" };
// -----------------------------------------------------
let sesion = null;

// La sesión la valida Laravel (middleware auth + rol:Administrador) y el layout entrega el usuario
// en window.LEDGERA. Ocultar cosas aquí es solo estética: lo que protege es el servidor.
function cargarSesion() {
  const u = window.LEDGERA && window.LEDGERA.usuario;
  if (!u || u.rol !== 'Administrador') { window.location.href = window.LEDGERA.base + '/'; return false; }
  sesion = { nombre: u.nombre, rol: u.rol };
  return true;
}

async function cerrarSesion() {
  try { await ledgeraFetch('/logout', { method: 'POST' }); } catch (_) {}
  window.location.href = window.LEDGERA.base + '/';
}

// Frases para el admin (diferentes a las del vendedor)
const FRASES_ADMIN = [
  `Panel de control, <span class="acento">{nombre}</span> `,
  `Todo bajo control, <span class="acento">{nombre}</span> `,
  `Bienvenido de vuelta, <span class="acento">{nombre}</span> `,
  `Sistema al día, <span class="acento">{nombre}</span> `,
  `Listo para gestionar, <span class="acento">{nombre}</span> `,
  `Vista general del sistema, <span class="acento">{nombre}</span> `
];

// -----------------------------------------------------
// Utilidades
// -----------------------------------------------------
function obtenerIniciales(nombreCompleto) {
  if (!nombreCompleto) return "?";
  const partes = nombreCompleto.trim().split(/\s+/);
  if (partes.length === 1) return partes[0][0].toUpperCase();
  return (partes[0][0] + partes[1][0]).toUpperCase();
}

function obtenerPrimerNombre(nombreCompleto) {
  if (!nombreCompleto) return "";
  return nombreCompleto.trim().split(/\s+/)[0];
}

// -----------------------------------------------------
// Renderizar info del admin en el header
// -----------------------------------------------------
function renderizarUsuario() {
  const nombreEl = document.getElementById("nombreUsuario");
  const avatarEl = document.getElementById("avatarUsuario");
  if (!nombreEl || !avatarEl) return;

  if (sesion && sesion.nombre) {
    nombreEl.textContent = sesion.nombre;
    avatarEl.textContent = sesion.iniciales || obtenerIniciales(sesion.nombre);
  } else {
    nombreEl.textContent = "Admin";
    avatarEl.textContent = "AD";
  }
}

// -----------------------------------------------------
// Saludo aleatorio al entrar
// -----------------------------------------------------
function mostrarSaludoAleatorio() {
  const saludoEl = document.getElementById("saludo");
  if (!saludoEl) return;

  const nombre = (sesion && sesion.nombre)
    ? obtenerPrimerNombre(sesion.nombre)
    : "Administrador";

  const frase = FRASES_ADMIN[Math.floor(Math.random() * FRASES_ADMIN.length)];
  saludoEl.innerHTML = frase.replace("{nombre}", nombre);
}

// -----------------------------------------------------
// Dropdown del perfil
// -----------------------------------------------------
function initDropdown() {
  const perfilBtn = document.getElementById("perfilBtn");
  const dropdown = document.getElementById("dropdown");

  if (!perfilBtn || !dropdown) return;

  perfilBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    const activo = dropdown.classList.toggle("activo");
    perfilBtn.setAttribute("aria-expanded", activo);
  });

  document.addEventListener("click", () => {
    dropdown.classList.remove("activo");
    perfilBtn.setAttribute("aria-expanded", "false");
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      dropdown.classList.remove("activo");
      perfilBtn.setAttribute("aria-expanded", "false");
    }
  });
}

// -----------------------------------------------------
// Navegación a módulos admin
// -----------------------------------------------------
const PAGINAS_MODULO = {
  usuarios:  window.LEDGERA.base + '/admin/usuarios',
  puestos:   window.LEDGERA.base + '/admin/puestos',
  auditoria: window.LEDGERA.base + '/admin/auditoria',
  reportes:  window.LEDGERA.base + '/admin/reportes'
};

function initCards() {
  document.querySelectorAll(".card").forEach((card) => {
    card.addEventListener("click", () => {
      const destino = PAGINAS_MODULO[card.dataset.modulo];
      if (destino) window.location.href = destino;
      else alert('Este módulo estará disponible pronto.');
    });
  });
}

// -----------------------------------------------------
// Init
// -----------------------------------------------------
document.addEventListener("DOMContentLoaded", async () => {
  if (!(await cargarSesion())) return;
  renderizarUsuario();
  mostrarSaludoAleatorio();
  initDropdown();
  initCards();

  const btnCerrar = document.querySelector(".dropdown .cerrar");
  if (btnCerrar) btnCerrar.addEventListener("click", (e) => { e.preventDefault(); cerrarSesion(); });
});


