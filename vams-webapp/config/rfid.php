<?php

return [

    /*
    |--------------------------------------------------------------------------
    | RFID Ingestion API Security
    |--------------------------------------------------------------------------
    |
    | Per-reader credentials (api_key / api_secret) live on the `rfid_readers`
    | table (see RfidReaderController), not here. These values only tune the
    | shared verification behaviour applied to every device request.
    |
    */

    'hmac_tolerance_seconds' => (int) env('RFID_HMAC_TOLERANCE_SECONDS', 120),

    'nonce_ttl_seconds' => (int) env('RFID_NONCE_TTL_SECONDS', 600),

    'debounce_seconds' => (int) env('RFID_DEBOUNCE_SECONDS', 20),

    /*
     * Maximum detections one reader may submit per minute before the API
     * starts refusing with 429 (manuscript §1.2.2 objective 8 — rate limiting
     * to improve resistance to flooding attacks).
     *
     * Sized well above real use rather than tightly: the listener already
     * suppresses repeat reads of a tag for RFID_LISTENER_DEBOUNCE_SECONDS, so
     * a busy gate produces one request per *distinct* vehicle. 120/min is two
     * per second sustained — far more than any gate generates, while still
     * bounding what a compromised or malfunctioning device can push.
     */
    'rate_limit_per_minute' => (int) env('RFID_RATE_LIMIT_PER_MINUTE', 120),

    /*
    |--------------------------------------------------------------------------
    | RFID Listener / Device Service
    |--------------------------------------------------------------------------
    |
    | Settings for `php artisan rfid:listen` — the long-running device service
    | that speaks the S4A UHF-202415 "MM" binary protocol over TCP and forwards
    | every tag read to the ingestion API as an HMAC-signed POST.
    |
    | This is the *client* half of the security contract above: it holds the
    | reader's plaintext api_key/api_secret (from .env, never committed) and
    | signs each request the same way VerifyRfidSignature verifies it.
    |
    */

    'listener' => [

        /*
         * How the TCP link to the reader is established:
         *
         *   'client' — VAMS dials the reader at reader.host:reader.port.
         *              Use when the reader's WiFi/Ethernet module is in
         *              "TCP Server" mode (the documented default).
         *
         *   'server' — VAMS listens on listen.port and waits for the reader
         *              to dial in. Use when the module is configured in
         *              "TCP Client" mode with this PC as its RemoteIP.
         */
        'mode' => env('RFID_LISTENER_MODE', 'client'),

        'reader' => [
            'host' => env('RFID_READER_HOST', '192.168.1.116'),
            'port' => (int) env('RFID_READER_PORT', 49152),
        ],

        'listen' => [
            'host' => env('RFID_LISTENER_BIND', '0.0.0.0'),
            'port' => (int) env('RFID_LISTENER_PORT', 49152),
        ],

        /*
         * Poll mode sends a "Read Type C UII" inventory command on an interval.
         * Leave enabled unless the reader is configured in active/auto mode,
         * in which case it pushes tag frames unprompted (protocol RTN 0x05)
         * and polling is merely redundant, not harmful.
         */
        'poll' => (bool) env('RFID_LISTENER_POLL', true),
        'poll_interval_ms' => (int) env('RFID_LISTENER_POLL_INTERVAL_MS', 500),

        'connect_timeout_seconds' => (int) env('RFID_LISTENER_CONNECT_TIMEOUT', 5),
        'reconnect_delay_seconds' => (int) env('RFID_LISTENER_RECONNECT_DELAY', 5),

        /*
         * Local read suppression, in seconds. A UHF reader re-reports the same
         * tag many times per second while it sits in the antenna field; without
         * this the service would flood the API (and the api_request_nonces
         * table) with reads the server would only mark as duplicates anyway.
         *
         * Keep this >= rfid.debounce_seconds. For a real vehicle gate, raise it
         * to comfortably exceed how long a vehicle lingers in the read zone,
         * otherwise a stationary vehicle can be toggled entry -> exit.
         */
        'local_debounce_seconds' => (int) env('RFID_LISTENER_DEBOUNCE_SECONDS', 20),

        'api' => [
            'url' => env('RFID_LISTENER_API_URL', 'http://127.0.0.1:8000/api/rfid/detections'),
            'key' => env('RFID_LISTENER_API_KEY'),
            'secret' => env('RFID_LISTENER_API_SECRET'),
            'timeout_seconds' => (int) env('RFID_LISTENER_API_TIMEOUT', 10),
            'verify_tls' => (bool) env('RFID_LISTENER_VERIFY_TLS', true),
        ],
    ],

];
