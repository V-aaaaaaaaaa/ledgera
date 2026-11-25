// =====================================================
// LEDGERA · Dashboard del Dueño y del Vendedor
// =====================================================
// La sesión y las rutas las entrega Laravel (layouts/app.blade.php y js/api.js).

// Páginas de cada módulo. Mientras una no exista, déjala en null:
// se muestra "pronto" en vez de un 404. Cuando la crees, pon su ruta.
const PAGINAS_MODULO = {
  ventas:        null,   // '/ventas'  (pendiente de portar a Laravel)
  inventario:    null,   // '/inventario'  (pendiente de portar a Laravel)
  fiado:         null,   // '/fiado'
  egresos:       null,   // '/egresos'
  exportaciones: null,   // '/exportaciones'  (solo Dueño)
  equipo:        '/gestion/usuarios',   // solo Dueño, desde el menú del perfil
  proveedores:   '/proveedores',        // solo Dueño, desde el menú del perfil
  cierre:        null    // '/cierre-caja'
};

let sesion = null;

const FRASES = [
  `Hola, <span class="acento">{nombre}</span> `,
  `¿Listo para vender, <span class="acento">{nombre}</span>? `,
  `Buen día, <span class="acento">{nombre}</span>. A darle con todo `,
  `¿Qué vendemos hoy, <span class="acento">{nombre}</span>? `,
  `Las cuentas claras, <span class="acento">{nombre}</span> `,
  `¡Vamos con toda, <span class="acento">{nombre}</span>! `
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
  return nombreCompleto ? nombreCompleto.trim().split(/\s+/)[0] : "";
}

function avisar(texto) {
  let t = document.getElementById("toastAviso");
  if (!t) {
    t = document.createElement("div");
    t.id = "toastAviso";
    t.style.cssText = "position:fixed;left:50%;bottom:32px;transform:translateX(-50%);" +
      "background:#1a3d1a;color:#fff;padding:14px 22px;border-radius:10px;font:600 15px Inter,sans-serif;" +
      "z-index:9999;box-shadow:0 6px 20px rgba(0,0,0,.3);";
    document.body.appendChild(t);
  }
  t.textContent = texto;
  t.style.display = "block";
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.display = "none"; }, 2500);
}

function irAModulo(modulo) {
  const destino = PAGINAS_MODULO[modulo];
  if (destino) window.location.href = window.LEDGERA.base + destino;
  else avisar("Este módulo estará disponible pronto.");
}

// -----------------------------------------------------
// Sesión (la valida el servidor, no el navegador)
// -----------------------------------------------------
function cargarSesion() {
  // Laravel ya validó la sesión y el rol; aquí solo se lee el usuario que entrega el layout.
  const u = window.LEDGERA && window.LEDGERA.usuario;
  if (!u) { window.location.href = window.LEDGERA.base + "/"; return false; }
  sesion = { nombre: u.nombre, rol: u.rol };
  return true;
}

async function cerrarSesion() {
  try { await ledgeraFetch("/logout", { method: "POST" }); } catch (_) {}
  window.location.href = window.LEDGERA.base + "/";
}

// -----------------------------------------------------
// Pintar la pantalla
// -----------------------------------------------------
function renderizarUsuario() {
  document.getElementById("nombreUsuario").textContent = sesion.nombre;
  document.getElementById("avatarUsuario").textContent = obtenerIniciales(sesion.nombre);
}

function mostrarSaludoAleatorio() {
  const saludoEl = document.getElementById("saludo");
  const frase = FRASES[Math.floor(Math.random() * FRASES.length)];
  saludoEl.innerHTML = frase.replace("{nombre}", obtenerPrimerNombre(sesion.nombre));
}

// Oculta lo que no corresponde al rol (solo estética: el PHP es quien protege)
function aplicarRol() {
  document.querySelectorAll("[data-solo]").forEach((el) => {
    if (el.dataset.solo !== sesion.rol) el.style.display = "none";
  });
}

// -----------------------------------------------------
// Eventos
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

  dropdown.querySelectorAll("[data-menu-modulo]").forEach((a) => {
    a.addEventListener("click", (e) => { e.preventDefault(); irAModulo(a.dataset.menuModulo); });
  });

  const cerrar = dropdown.querySelector(".cerrar");
  if (cerrar) cerrar.addEventListener("click", (e) => { e.preventDefault(); cerrarSesion(); });
}

function initCards() {
  document.querySelectorAll(".card").forEach((card) => {
    card.addEventListener("click", () => irAModulo(card.dataset.modulo));
  });
  const btnCierre = document.getElementById("btnCierre");
  if (btnCierre) btnCierre.addEventListener("click", () => irAModulo("cierre"));
}

// -----------------------------------------------------
// Init
// -----------------------------------------------------
document.addEventListener("DOMContentLoaded", async () => {
  if (!(await cargarSesion())) return;
  renderizarUsuario();
  mostrarSaludoAleatorio();
  aplicarRol();
  initDropdown();
  initCards();
});