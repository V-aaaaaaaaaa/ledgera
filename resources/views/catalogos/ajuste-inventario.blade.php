{{-- =====================================================
     Ajuste de inventario (RF-008): merma o corrección por conteo.
     Se abre con el botón "Ajustar" de cada presentación.
     ===================================================== --}}
<dialog id="ajustar" class="cat-modal aj-modal" aria-labelledby="aj-titulo">
    <form method="POST" action="{{ route('inventario.ajuste') }}" id="aj-form">
        @csrf
        <input type="hidden" name="id_inventario" id="aj-id">
        <input type="hidden" name="tipo_ajuste" id="aj-tipo" value="Merma">

        <h2 id="aj-titulo">Ajustar inventario</h2>
        <p class="aj-producto"><strong id="aj-nombre"></strong><br>
            El sistema dice que hay <strong id="aj-stock"></strong>.</p>

        <div class="aj-opciones" role="radiogroup" aria-label="Tipo de ajuste">
            <button type="button" class="aj-opcion activo" data-tipo="Merma">
                <i class="fas fa-trash-can"></i>
                <strong>Se dañó o se perdió</strong>
                <span>Merma: descuenta del inventario</span>
            </button>
            <button type="button" class="aj-opcion" data-tipo="Correccion_conteo">
                <i class="fas fa-list-ol"></i>
                <strong>Conté y no cuadra</strong>
                <span>Conteo: corrige al número real</span>
            </button>
        </div>

        <label for="aj-cantidad" id="aj-etq-cantidad">¿Cuántos se dañaron o perdieron?</label>
        <input type="number" id="aj-cantidad" name="cantidad" min="0" step="1" inputmode="numeric" required data-filtro="numeros">
        <p class="aj-resultado" id="aj-resultado"></p>

        <label for="aj-motivo">Motivo *</label>
        <div class="aj-chips" id="aj-chips"></div>
        <input type="text" id="aj-motivo" name="motivo" maxlength="255" required
               placeholder="Ej: se pudrió por el calor" data-filtro="texto">

        <div class="cat-modal-actions">
            <button type="button" class="cat-secondary" onclick="this.closest('dialog').close()">Cancelar</button>
            <button type="submit" class="cat-primary" id="aj-guardar">Guardar ajuste</button>
        </div>
    </form>
</dialog>

<style>
    .cat-btn-ajustar { margin-right: 6px; }
    .aj-producto { margin: 0 0 12px; line-height: 1.4; }
    .aj-opciones { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
    .aj-opcion { display: flex; flex-direction: column; gap: 2px; align-items: flex-start; min-height: 64px; padding: 10px 12px;
        border: 2px solid #C8DED0; border-radius: 10px; background: #fff; color: #0F3D2E; font: inherit; text-align: left; cursor: pointer; }
    .aj-opcion span { font-size: 12px; color: #4A6A4A; }
    .aj-opcion.activo { border-color: #27AE60; background: #E8F5EE; }
    .aj-resultado { margin: 6px 0 10px; font-weight: 700; }
    .aj-resultado.baja { color: #b3261e; } .aj-resultado.sube { color: #1a6a2a; }
    .aj-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
    .aj-chips button { min-height: 36px; padding: 4px 12px; border: 1px solid #C8DED0; border-radius: 999px; background: #F5F7F6; font: inherit; font-size: 13px; cursor: pointer; }
    #aj-cantidad { font-size: 1.4rem; font-weight: 700; }
    @media (max-width: 480px) { .aj-opciones { grid-template-columns: 1fr; } }
</style>

<script>
(function () {
    const dlg = document.getElementById('ajustar');
    if (!dlg) return;
    const $ = (id) => document.getElementById(id);
    const MOTIVOS = {
        Merma: ['Se dañó', 'Se pudrió', 'Se perdió', 'Mojado'],
        Correccion_conteo: ['Conteo físico', 'Error al registrar', 'Inventario inicial']
    };
    let stock = 0;

    function elegir(tipo) {
        $('aj-tipo').value = tipo;
        dlg.querySelectorAll('.aj-opcion').forEach((b) => b.classList.toggle('activo', b.dataset.tipo === tipo));
        $('aj-etq-cantidad').textContent = tipo === 'Merma' ? '¿Cuántos se dañaron o perdieron?' : '¿Cuántos hay de verdad? (lo que contaste)';
        $('aj-chips').innerHTML = MOTIVOS[tipo].map((m) => `<button type="button">${m}</button>`).join('');
        calcular();
    }

    // Muestra en vivo cómo queda el inventario
    function calcular() {
        const n = parseInt($('aj-cantidad').value, 10);
        const r = $('aj-resultado');
        r.className = 'aj-resultado';
        if (isNaN(n)) { r.textContent = ''; return; }
        const queda = $('aj-tipo').value === 'Merma' ? stock - n : n;
        if ($('aj-tipo').value === 'Merma' && n > stock) { r.textContent = `Solo hay ${stock}.`; r.classList.add('baja'); return; }
        r.textContent = `Quedará en ${queda} (${queda - stock >= 0 ? '+' : ''}${queda - stock}).`;
        r.classList.add(queda < stock ? 'baja' : 'sube');
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-ajustar]');
        if (btn) {
            stock = parseInt(btn.dataset.stock, 10) || 0;
            $('aj-id').value = btn.dataset.id;
            $('aj-nombre').textContent = btn.dataset.nombre;
            $('aj-stock').textContent = stock;
            $('aj-cantidad').value = '';
            $('aj-motivo').value = '';
            elegir('Merma');
            dlg.showModal();
            $('aj-cantidad').focus();
        }
        const op = e.target.closest('.aj-opcion');
        if (op) elegir(op.dataset.tipo);
        const chip = e.target.closest('#aj-chips button');
        if (chip) $('aj-motivo').value = chip.textContent;
    });

    $('aj-cantidad').addEventListener('input', calcular);
    // Evita guardar dos veces por doble toque
    $('aj-form').addEventListener('submit', () => { $('aj-guardar').disabled = true; });
})();
</script>
