/**
 * RFID tag "scan a tag" helper panel (rfid-tags create/edit form).
 *
 * Kept in an external, Vite-bundled script (rather than an inline <script>)
 * because the app's CSP (see SecurityHeaders middleware) only allows
 * script-src 'self' -- inline scripts are blocked by the browser and would
 * silently fail to run.
 */
document.addEventListener('DOMContentLoaded', function () {
    const panel = document.querySelector('[data-scan-panel]');
    if (!panel) return;

    const toggle = panel.querySelector('[data-scan-toggle]');
    const body = panel.querySelector('[data-scan-body]');
    const status = panel.querySelector('[data-scan-status]');
    const results = panel.querySelector('[data-scan-results]');
    const epcField = document.getElementById('epc');
    const url = panel.dataset.scanUrl;

    let timer = null;

    function stop(message) {
        clearInterval(timer);
        timer = null;
        toggle.textContent = 'Start scanning';
        if (message) status.textContent = message;
    }

    function start() {
        body.hidden = false;
        toggle.textContent = 'Stop scanning';
        status.textContent = 'Waiting for a tag…';
        results.innerHTML = '';
        poll();
        timer = setInterval(poll, 1500);
    }

    function choose(epc) {
        epcField.value = epc;
        epcField.focus();
        stop('Filled in ' + epc + '. Now choose the credential type and save.');
        results.innerHTML = '';
    }

    function render(scans) {
        if (!scans.length) {
            status.textContent = 'Waiting for a tag…';
            results.innerHTML = '';
            return;
        }

        status.textContent = scans.length === 1
            ? '1 tag seen in the last 2 minutes:'
            : scans.length + ' tags seen in the last 2 minutes:';

        results.innerHTML = '';

        scans.forEach(function (scan) {
            const item = document.createElement('li');

            if (scan.registered_as) {
                item.className = 'flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 opacity-70';
                item.innerHTML =
                    '<span class="font-mono text-sm text-gray-500"></span>' +
                    '<span class="text-xs font-semibold text-gray-500 whitespace-nowrap"></span>';
                item.children[0].textContent = scan.epc;
                item.children[1].textContent = 'already registered — ' + scan.registered_as;
            } else {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'flex w-full items-center justify-between gap-3 rounded-lg border border-brand-300 bg-white px-3 py-2 text-left hover:border-brand-600 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500';
                button.innerHTML =
                    '<span class="font-mono text-sm font-semibold text-brand-900"></span>' +
                    '<span class="text-xs text-gray-500 whitespace-nowrap"></span>';
                button.children[0].textContent = scan.epc;
                button.children[1].textContent = 'seen ' + scan.seconds_ago + 's ago · use this';
                button.addEventListener('click', function () { choose(scan.epc); });
                item.appendChild(button);
            }

            results.appendChild(item);
        });
    }

    function poll() {
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (data) { render(data.scans || []); })
            .catch(function () {
                stop('Could not reach the server. Is the web server still running?');
            });
    }

    toggle.addEventListener('click', function () {
        timer ? stop('Scanning stopped.') : start();
    });

    // Do not keep polling once the page is hidden or being left.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden && timer) stop('Scanning paused.');
    });
});
