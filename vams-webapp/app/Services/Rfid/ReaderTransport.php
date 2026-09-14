<?php

namespace App\Services\Rfid;

use RuntimeException;

/**
 * The TCP link to the physical RFID reader, in whichever direction the
 * reader's network module is configured for.
 *
 * The S4A UHF-202415 reaches the LAN through an embedded WiFi/serial bridge
 * that can sit in either "TCP Server" mode (it listens, we dial it — the
 * documented default at 192.168.1.116:49152) or "TCP Client" mode (it dials
 * out to a configured RemoteIP/RemotePort). Both are supported here so the
 * service does not have to be rewritten if the module is reconfigured; see
 * NETWORK_CONFIG.md.
 *
 * Byte framing is deliberately *not* this class's job — it hands raw bytes to
 * MmProtocolCodec, which owns the frame rules.
 */
class ReaderTransport
{
    /** @var resource|null The connected reader socket. */
    private $socket = null;

    /** @var resource|null The accept socket, in 'server' mode only. */
    private $listener = null;

    private string $peer = '';

    public function __construct(
        private readonly string $mode,
        private readonly string $host,
        private readonly int $port,
        private readonly int $connectTimeout,
    ) {
        if (! in_array($this->mode, ['client', 'server'], true)) {
            throw new RuntimeException("Unsupported RFID listener mode [{$this->mode}]; expected 'client' or 'server'.");
        }
    }

    public static function fromConfig(): self
    {
        $listener = config('rfid.listener');
        $mode = $listener['mode'];

        return new self(
            $mode,
            $mode === 'client' ? $listener['reader']['host'] : $listener['listen']['host'],
            $mode === 'client' ? $listener['reader']['port'] : $listener['listen']['port'],
            $listener['connect_timeout_seconds'],
        );
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * Human-readable description of where this transport expects the reader.
     */
    public function endpoint(): string
    {
        return $this->mode === 'client'
            ? "tcp://{$this->host}:{$this->port} (dialling the reader)"
            : "tcp://{$this->host}:{$this->port} (waiting for the reader to dial in)";
    }

    /**
     * The remote address of the currently connected reader, once connected.
     */
    public function peer(): string
    {
        return $this->peer;
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && ! feof($this->socket);
    }

    /**
     * Establish the link, blocking for at most the connect timeout.
     *
     * @throws RuntimeException when the reader cannot be reached in time.
     */
    public function connect(): void
    {
        $this->disconnect();

        $this->mode === 'client' ? $this->dial() : $this->accept();

        stream_set_blocking($this->socket, false);
    }

    private function dial(): void
    {
        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->connectTimeout,
            STREAM_CLIENT_CONNECT,
        );

        if (! $socket) {
            throw new RuntimeException("Cannot reach the reader at {$this->host}:{$this->port} — {$errstr} (errno {$errno}).");
        }

        $this->socket = $socket;
        $this->peer = "{$this->host}:{$this->port}";
    }

    private function accept(): void
    {
        // The accept socket is kept open across reader reconnects, so a reader
        // that drops and redials is picked straight back up.
        if (! is_resource($this->listener)) {
            $listener = @stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);

            if (! $listener) {
                throw new RuntimeException("Cannot listen on {$this->host}:{$this->port} — {$errstr} (errno {$errno}).");
            }

            $this->listener = $listener;
        }

        $socket = @stream_socket_accept($this->listener, $this->connectTimeout, $peer);

        if (! $socket) {
            throw new RuntimeException("No reader dialled in on {$this->host}:{$this->port} within {$this->connectTimeout}s.");
        }

        $this->socket = $socket;
        $this->peer = (string) $peer;
    }

    /**
     * Send a raw protocol frame to the reader.
     */
    public function send(string $frame): void
    {
        if (! is_resource($this->socket)) {
            throw new RuntimeException('Cannot write to the reader: not connected.');
        }

        $written = @fwrite($this->socket, $frame);

        if ($written === false || $written !== strlen($frame)) {
            throw new RuntimeException('Lost the reader connection while sending a command.');
        }
    }

    /**
     * Read whatever bytes are waiting, blocking at most $timeoutSeconds for the
     * first of them. Returns '' when the reader is simply idle.
     *
     * @throws RuntimeException when the reader closes the connection.
     */
    public function read(float $timeoutSeconds): string
    {
        if (! is_resource($this->socket)) {
            throw new RuntimeException('Cannot read from the reader: not connected.');
        }

        $read = [$this->socket];
        $write = $except = null;
        $seconds = (int) $timeoutSeconds;
        $micros = (int) round(($timeoutSeconds - $seconds) * 1_000_000);

        if (@stream_select($read, $write, $except, $seconds, $micros) === false) {
            return '';
        }

        if ($read === []) {
            return '';
        }

        $chunk = @fread($this->socket, 8192);

        if ($chunk === false || ($chunk === '' && feof($this->socket))) {
            throw new RuntimeException('The reader closed the connection.');
        }

        return (string) $chunk;
    }

    /**
     * Drop the reader link (the accept socket, if any, stays open).
     */
    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }

        $this->socket = null;
        $this->peer = '';
    }

    /**
     * Tear down everything, accept socket included.
     */
    public function close(): void
    {
        $this->disconnect();

        if (is_resource($this->listener)) {
            @fclose($this->listener);
        }

        $this->listener = null;
    }
}
