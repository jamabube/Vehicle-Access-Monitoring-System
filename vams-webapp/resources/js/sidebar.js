/**
 * Mobile sidebar toggle for the app layout.
 *
 * Kept in an external, Vite-bundled script (rather than an inline <script>
 * or onclick="" attribute) because the app's CSP (see SecurityHeaders
 * middleware) only allows script-src 'self' -- inline scripts/handlers are
 * blocked by the browser and would silently fail to run.
 */
document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('vams-sidebar');
    var toggle = document.getElementById('vams-sidebar-toggle');

    if (!sidebar || !toggle) {
        return;
    }

    toggle.addEventListener('click', function () {
        sidebar.classList.toggle('hidden');
        sidebar.dataset.userToggled = '1';
    });

    function syncSidebar() {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('hidden');
        } else if (!sidebar.dataset.userToggled) {
            sidebar.classList.add('hidden');
        }
    }

    syncSidebar();
    window.addEventListener('resize', syncSidebar);
});
