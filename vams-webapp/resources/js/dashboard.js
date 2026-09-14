/**
 * Dashboard "Gate Activity" live refresh.
 *
 * Kept in an external, Vite-bundled script (rather than an inline <script>)
 * because the app's CSP (see SecurityHeaders middleware) only allows
 * script-src 'self' -- inline scripts are blocked by the browser and would
 * silently fail to run.
 */
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-dashboard]');
    if (!root) return;

    const url = root.dataset.refreshUrl;
    const activity = root.querySelector('[data-gate-activity]');
    const inside = root.querySelector('[data-vehicles-inside]');
    const liveDot = root.querySelector('[data-live-dot]');
    const liveLabel = root.querySelector('[data-live-label]');

    // Two update paths, on purpose:
    //   - WebSocket (Reverb) pushes the instant a tag is read.
    //   - Polling is the safety net, in case the WebSocket server is not
    //     running or the socket drops. It slows right down once the socket
    //     is connected, and speeds back up if that connection is lost.
    const POLL_FAST_MS = 2000;   // no socket: poll hard enough to feel live
    const POLL_SLOW_MS = 30000;  // socket connected: just a reconciliation
    let timer = null;
    let socketConnected = false;

    function setStat(name, value) {
        root.querySelectorAll('[data-stat="' + name + '"]').forEach(function (el) {
            el.textContent = Number(value).toLocaleString();
        });
    }

    function offline(message) {
        if (liveDot) liveDot.style.visibility = 'hidden';
        if (liveLabel) liveLabel.textContent = message;
    }

    function online() {
        if (liveDot) liveDot.style.visibility = 'visible';
        if (liveLabel) {
            liveLabel.textContent = socketConnected
                ? 'Live — instant updates'
                : 'Live — checking every 2s';
        }
    }

    function setPollInterval(ms) {
        clearInterval(timer);
        timer = setInterval(refresh, ms);
    }

    function refresh() {
        if (document.hidden) return;

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (data) {
                // Replacing innerHTML resets scrollTop, which would yank a
                // guard back to the top of the log every couple of seconds
                // while they are reading it. Put them back where they were.
                const activityScroll = activity ? activity.scrollTop : 0;
                const insideScroll = inside ? inside.scrollTop : 0;

                if (activity) {
                    activity.innerHTML = data.activity_html;
                    activity.scrollTop = activityScroll;
                }

                if (inside) {
                    inside.innerHTML = data.inside_html;
                    inside.scrollTop = insideScroll;
                }

                setStat('entries', data.today.entries);
                setStat('exits', data.today.exits);
                setStat('authorized', data.today.authorized);
                setStat('denied', data.today.denied);
                setStat('inside', data.inside_count);

                online();
            })
            .catch(function () {
                offline('Not updating — connection lost');
            });
    }

    setPollInterval(POLL_FAST_MS);

    // Catch up immediately when the guard returns to the tab.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refresh();
    });

    // --- Real-time push over WebSockets (Laravel Reverb) ---------------
    // The broadcast is a nudge, not the data: on hearing it we re-fetch the
    // rendered log, so there is one source of truth for the markup and a
    // socket that dropped and reconnected still converges on correct state.
    if (window.Echo) {
        try {
            window.Echo.private('gate-activity')
                .listen('.gate.activity', function () {
                    refresh();
                });

            const connection = window.Echo.connector?.pusher?.connection;

            if (connection) {
                connection.bind('connected', function () {
                    socketConnected = true;
                    setPollInterval(POLL_SLOW_MS);
                    online();
                    refresh();
                });

                ['disconnected', 'unavailable', 'failed'].forEach(function (state) {
                    connection.bind(state, function () {
                        socketConnected = false;
                        setPollInterval(POLL_FAST_MS);
                        online();
                    });
                });
            }
        } catch (e) {
            // Reverb not running, or auth refused. Polling already covers us.
            socketConnected = false;
        }
    }
});
