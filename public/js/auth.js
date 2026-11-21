// =====================================================
// LEDGERA · Inicio de sesión
// El servidor (AuthController) valida y dice a qué pantalla ir.
// =====================================================
const loginForm = document.getElementById('loginForm');

function showMessage(elementId, message) {
    const element = document.getElementById(elementId);
    if (!element) return;
    element.textContent = message;
    element.style.display = 'block';
    setTimeout(() => { element.style.display = 'none'; }, 5000);
}

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const usuario = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;

    if (!usuario) return showMessage('loginError', 'Por favor ingresa tu usuario');
    if (!password) return showMessage('loginError', 'Por favor ingresa tu contraseña');

    try {
        const { status, data } = await ledgeraFetch('/login', {
            method: 'POST',
            body: JSON.stringify({ usuario, password })
        });

        if (data.ok) {
            showMessage('loginSuccess', data.cambiar_clave
                ? 'Debes crear una clave propia...'
                : '¡Acceso correcto! Redirigiendo...');
            setTimeout(() => { window.location.href = data.destino; }, 800);
        } else if (status === 419) {
            showMessage('loginError', 'La página caducó. Recárgala e inténtalo de nuevo.');
        } else {
            showMessage('loginError', data.mensaje || 'Usuario o contraseña incorrectos');
        }
    } catch (error) {
        showMessage('loginError', 'No hay conexión con el servidor. Revisa tu internet e intenta de nuevo.');
        console.error('Error:', error);
    }
});
