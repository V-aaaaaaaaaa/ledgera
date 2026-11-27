
document.addEventListener('DOMContentLoaded', () => {

    const shell = document.getElementById('opShell');
    const sidebar = document.getElementById('opSidebar');

    const collapse = document.getElementById('opCollapse');
    const mobileToggle = document.getElementById('opMobileToggle');
    const mobileClose = document.getElementById('opMobileClose');
    const overlay = document.getElementById('opOverlay');

    if (!shell || !sidebar) return;

    const mobile = window.matchMedia('(max-width: 850px)');

    // Recuperar preferencia del menú
    let collapsed = false;

    try {
        collapsed = localStorage.getItem('ledgera-sidebar') === 'collapsed';
    } catch (_) {}

    function setCollapsed(value) {

        collapsed = value;

        shell.classList.toggle(
            'is-collapsed', collapsed
        );

        collapse.setAttribute(
            'aria-expanded', String(!collapsed)
        );

        collapse.setAttribute(
            'aria-label',
            collapsed ? 'Expandir menú' : 'Contraer menú'
        );

        try {
            localStorage.setItem(
                'ledgera-sidebar',
                collapsed ? 'collapsed' : 'expanded'
            );
        } catch (_) {}
    }

    setCollapsed(collapsed);

    collapse.addEventListener('click', () => {
        setCollapsed(!collapsed);
    });

    // Menú móvil
    function abrirMenu() {

        if (!mobile.matches) return;

        shell.classList.add('mobile-open');
        overlay.hidden = false;
        sidebar.inert = false;

        mobileToggle.setAttribute(
            'aria-expanded', 'true'
        );

        mobileClose.focus();
    }

    function cerrarMenu(devolverFoco = true) {

        const abierto = shell.classList.contains('mobile-open');

        shell.classList.remove('mobile-open');
        overlay.hidden = true;

        sidebar.inert = mobile.matches;

        mobileToggle.setAttribute(
            'aria-expanded', 'false'
        );

        if (abierto && devolverFoco && mobile.matches) {
            mobileToggle.focus();
        }
    }

    mobileToggle.addEventListener('click', abrirMenu);

    mobileClose.addEventListener('click', () => {
        cerrarMenu();
    });

    overlay.addEventListener('click', () => {
        cerrarMenu();
    });

    document.addEventListener('keydown', (event) => {

        if (!shell.classList.contains('mobile-open')) {
            return;
        }

        if (event.key === 'Escape') {
            cerrarMenu();
        }

        // Mantener el foco dentro del menú móvil
        if (event.key === 'Tab') {

            const elementos = Array.from(
                sidebar.querySelectorAll('a[href], button')
            ).filter(el => el.getClientRects().length > 0);

            const primero = elementos[0];
            const ultimo = elementos[elementos.length - 1];

            if (event.shiftKey && document.activeElement === primero) {
                event.preventDefault();
                ultimo?.focus();
            } else if (!event.shiftKey && document.activeElement === ultimo) {
                event.preventDefault();
                primero?.focus();
            }
        }
    });

    mobile.addEventListener('change', () => {
        cerrarMenu(false);
    });

    cerrarMenu(false);
});
