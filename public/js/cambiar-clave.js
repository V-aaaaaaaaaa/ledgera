// =====================================================
// LEDGERA · Cambio de clave
// La vista (Blade) entrega window.LEDGERA.obligatorio y window.LEDGERA.destino.
// =====================================================
const $ = (id) => document.getElementById(id);

function showMessage(elementId, message) {
    const el = $(elementId);
    if (!el) return;
    el.textContent = message;
    el.style.display = 'block';
    setTimeout(() => { el.style.display = 'none'; }, 5000);
}

function marcar(idRegla, cumple) {
    const li = $(idRegla);
    li.classList.toggle('ok', cumple);
    li.querySelector('i').className = cumple ? 'fas fa-circle-check' : 'far fa-circle';
}

// Revisa las reglas en vivo y dice si se cumplen todas
function evaluar() {
    const a = $('claveActual').value;
    const n = $('claveNueva').value;
    const c = $('claveConfirmar').value;
    const reglas = {
        regLen: n.length >= 8,
        regMix: /[A-Za-z]/.test(n) && /[0-9]/.test(n),
        regDist: n !== '' && n !== a,
        regIgual: n !== '' && n === c
    };
    Object.entries(reglas).forEach(([id, ok]) => marcar(id, ok));
    return Object.values(reglas).every(Boolean);
}

async function cerrarSesion() {
    try { await ledgeraFetch('/logout', { method: 'POST' }); } catch (_) {}
    window.location.href = window.LEDGERA.base + '/';
}

function iniciar() {
    if (window.LEDGERA.obligatorio) {
        $('avisoTemporal').style.display = 'block';
        $('claveActual').placeholder = 'Clave temporal';
        $('btnSalir').style.display = 'inline-block';   // sin "Cancelar": no puede saltarse el cambio
    } else {
        $('btnCancelar').style.display = 'inline-block';
    }
}

['claveActual', 'claveNueva', 'claveConfirmar'].forEach((id) => $(id).addEventListener('input', evaluar));

document.querySelectorAll('[data-ver]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const input = $(btn.dataset.ver);
        const mostrar = input.type === 'password';
        input.type = mostrar ? 'text' : 'password';
        btn.querySelector('i').className = mostrar ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
});

$('btnSalir').addEventListener('click', cerrarSesion);
$('btnCancelar').addEventListener('click', () => { window.location.href = window.LEDGERA.destino; });

$('claveForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!evaluar()) return showMessage('claveError', 'Revisa las reglas de la clave nueva.');

    try {
        const { status, data } = await ledgeraFetch('/cambiar-clave', {
            method: 'POST',
            body: JSON.stringify({
                actual: $('claveActual').value,
                nueva: $('claveNueva').value,
                confirmar: $('claveConfirmar').value
            })
        });

        if (data.ok) {
            showMessage('claveSuccess', '¡Clave cambiada! Redirigiendo...');
            setTimeout(() => { window.location.href = data.destino; }, 900);
        } else if (status === 419 || (status === 401 && !data.mensaje)) {
            showMessage('claveError', 'La página caducó. Vuelve a iniciar sesión.');
            setTimeout(() => { window.location.href = window.LEDGERA.base + '/'; }, 2000);
        } else {
            showMessage('claveError', data.mensaje || 'No se pudo cambiar la clave.');
            if (data.cerrar) setTimeout(() => { window.location.href = window.LEDGERA.base + '/'; }, 2500);
        }
    } catch (err) {
        showMessage('claveError', 'No hay conexión con el servidor. Intenta de nuevo.');
        console.error(err);
    }
});

iniciar();
