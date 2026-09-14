<?php

namespace App\Console\Commands;

use App\Models\RfidReader;
use App\Services\Rfid\HmacSignatureVerifier;
use App\Services\Rfid\MmProtocolCodec;
use App\Services\Rfid\ReaderTransport;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read-only connectivity diagnostics for the RFID reader link.
 *
 * Answers "why is the reader not showing up?" in one place: the network path
 * to the reader, whether it speaks the MM protocol, whether the listener's
 * credentials match a registered `rfid_readers` row, and whether the ingestion
 * API is up. It writes nothing and posts nothing, so it is safe to run at any
 * time, including in production.
 *
 *   php artisan rfid:doctor
 */
class RfidDoctorCommand extends Command
{
    protected $signature = 'rfid:doctor {--discovery-timeout=3 : Seconds to wait for a UDP discovery reply}';

    protected $description = 'Diagnose connectivity between VAMS, the RFID reader, and the ingestion API';

    /** The HF-A11 style UDP discovery port used by the reader network module. */
    private const DISCOVERY_PORT = 48899;

    private const DISCOVERY_MAGIC = 'HF-A11ASSISTHREAD';

    private int $failures = 0;

    public function handle(MmProtocolCodec $codec, HmacSignatureVerifier $signer): int
    {
        $config = config('rfid.listener');

        $this->newLine();
        $this->components->info('VAMS RFID connectivity check');

        $this->section('Configuration');
        $this->reportConfiguration($config);

        $this->section('Reader network path');
        $this->checkReaderSocket($config);
        $this->checkDiscovery($config);

        $this->section('Reader protocol');
        $this->checkInventory($codec);

        $this->section('Ingestion API');
        $this->checkApiEndpoint($config);
        $this->checkCredentials($config, $signer);

        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error($this->failures.' check(s) failed. See NETWORK_CONFIG.md "Troubleshooting".');

            return self::FAILURE;
        }

        $this->components->info('All checks passed. Start the service with: php artisan rfid:listen');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function reportConfiguration(array $config): void
    {
        $this->line('  Mode:          '.$config['mode'].($config['mode'] === 'client'
            ? '  (VAMS dials the reader)'
            : '  (the reader dials VAMS)'));
        $this->line('  Reader:        '.$config['reader']['host'].':'.$config['reader']['port']);
        $this->line('  Listen on:     '.$config['listen']['host'].':'.$config['listen']['port']);
        $this->line('  API endpoint:  '.$config['api']['url']);
        $this->line('  API key:       '.($config['api']['key'] ? Str::limit($config['api']['key'], 14, '...') : '<not set>'));
        $this->line('  API secret:    '.($config['api']['secret'] ? 'set' : '<not set>'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function checkReaderSocket(array $config): void
    {
        if ($config['mode'] === 'server') {
            $host = $config['listen']['host'];
            $port = $config['listen']['port'];

            $socket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);

            if ($socket) {
                fclose($socket);
                $this->reportPass("Port {$port} is free to listen on; the reader must be configured to dial this PC.");
            } else {
                $this->reportFail("Cannot listen on {$host}:{$port} — {$errstr}. Another process is probably holding the port.");
            }

            return;
        }

        $host = $config['reader']['host'];
        $port = $config['reader']['port'];
        $timeout = (int) $config['connect_timeout_seconds'];

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);

        if ($socket) {
            fclose($socket);
            $this->reportPass("Reader control port {$host}:{$port} is open.");

            return;
        }

        $this->reportFail("Reader control port {$host}:{$port} did not answer — {$errstr}.");
        $this->hint('Check the reader is powered on and its IP has not changed (DHCP leases move).');
        $this->hint('If its module is in TCP Client mode, set RFID_LISTENER_MODE=server in .env instead.');
    }

    /**
     * Broadcast the module's UDP discovery string; a reply reveals the reader's
     * current IP even if its control port is closed or its address has moved.
     *
     * @param  array<string, mixed>  $config
     */
    private function checkDiscovery(array $config): void
    {
        $timeout = (int) $this->option('discovery-timeout');

        $socket = @stream_socket_server('udp://0.0.0.0:0', $errno, $errstr, STREAM_SERVER_BIND);

        if (! $socket) {
            $this->warn("  ~ Could not open a UDP socket for discovery — {$errstr}. Skipping.");

            return;
        }

        foreach ([$config['reader']['host'], '255.255.255.255'] as $target) {
            @stream_socket_sendto($socket, self::DISCOVERY_MAGIC, 0, "udp://{$target}:".self::DISCOVERY_PORT);
        }

        $replies = [];
        $read = [$socket];
        $write = $except = null;

        while (@stream_select($read, $write, $except, $timeout, 0) > 0) {
            $data = @stream_socket_recvfrom($socket, 1024, 0, $peer);

            if ($data === false || $data === '') {
                break;
            }

            $replies[$peer] = trim($data);
            $read = [$socket];
            $timeout = 1;
        }

        fclose($socket);

        if ($replies === []) {
            $this->warn('  ~ No UDP discovery reply on port '.self::DISCOVERY_PORT.'. Normal if the reader is off, on another subnet, or has discovery disabled.');

            return;
        }

        foreach ($replies as $peer => $reply) {
            $this->reportPass("Discovery reply from {$peer}: {$reply}");
        }
    }

    /**
     * Send one real inventory command and report what comes back.
     */
    private function checkInventory(MmProtocolCodec $codec): void
    {
        $command = $codec->inventoryCommand();
        $this->line('  Inventory command: '.strtoupper(chunk_split(bin2hex($command), 2, ' ')));

        try {
            $transport = ReaderTransport::fromConfig();
            $transport->connect();
        } catch (Throwable $e) {
            $this->reportFail('Could not open the reader link: '.$e->getMessage());

            return;
        }

        try {
            $transport->send($command);

            $buffer = '';
            $deadline = microtime(true) + 2;

            while (microtime(true) < $deadline) {
                $buffer .= $transport->read(0.2);
            }

            $frames = $codec->extractFrames($buffer);

            if ($frames === []) {
                $this->reportFail('The reader accepted the connection but sent no valid protocol frames.');
                $this->hint('Confirm the control port really is the MM protocol port (try RFIDDemo.exe on the same IP/port).');

                return;
            }

            $this->reportPass(count($frames).' protocol frame(s) received — the reader speaks the MM protocol.');

            $tags = [];

            foreach ($frames as $frame) {
                $tag = $codec->decodeTagReport($frame);

                if ($tag !== null) {
                    $tags[$tag['epc']] = $tag;
                }
            }

            if ($tags === []) {
                $this->warn('  ~ No tags in range. Hold a tag in front of the antenna and re-run to confirm reads.');

                return;
            }

            foreach ($tags as $tag) {
                $this->reportPass("Tag read: {$tag['epc']}  (antenna {$tag['antenna']}, RSSI {$tag['rssi']} dBm)");
            }
        } catch (Throwable $e) {
            $this->reportFail('Reader link failed mid-exchange: '.$e->getMessage());
        } finally {
            $transport->close();
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function checkApiEndpoint(array $config): void
    {
        $parts = parse_url($config['api']['url']);

        if (! isset($parts['host'])) {
            $this->reportFail('RFID_LISTENER_API_URL is not a valid URL.');

            return;
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);

        if ($socket) {
            fclose($socket);
            $this->reportPass("API host {$host}:{$port} is accepting connections.");

            return;
        }

        $this->reportFail("API host {$host}:{$port} did not answer — {$errstr}.");
        $this->hint('Start the web server: php artisan serve --host=0.0.0.0 --port=8000');
    }

    /**
     * Confirm the listener's credentials match a registered reader, and that a
     * signature made with the .env secret verifies against the stored one.
     *
     * @param  array<string, mixed>  $config
     */
    private function checkCredentials(array $config, HmacSignatureVerifier $signer): void
    {
        $apiKey = $config['api']['key'];
        $apiSecret = $config['api']['secret'];

        if (! $apiKey || ! $apiSecret) {
            $this->reportFail('RFID_LISTENER_API_KEY / RFID_LISTENER_API_SECRET are not both set in .env.');
            $this->hint('Generate a reader and its credentials with: php artisan db:seed --class=RfidReaderSeeder');

            return;
        }

        try {
            $reader = RfidReader::where('api_key', $apiKey)->first();
        } catch (Throwable $e) {
            $this->reportFail('Could not query rfid_readers: '.$e->getMessage());

            return;
        }

        if (! $reader) {
            $this->reportFail('No rfid_readers row matches RFID_LISTENER_API_KEY.');
            $this->hint('Re-seed the reader, or copy the key from the RFID Readers page in the web UI.');

            return;
        }

        $this->reportPass("Credentials match reader {$reader->device_code} ({$reader->device_name}), status: {$reader->status}.");

        if ($reader->status === 'disabled') {
            $this->reportFail("Reader {$reader->device_code} is disabled; the API will reject every detection until it is re-enabled.");

            return;
        }

        try {
            $stored = Crypt::decryptString($reader->api_secret_hash);
        } catch (DecryptException) {
            $this->reportFail('The stored api_secret could not be decrypted — APP_KEY has probably changed since the reader was created.');
            $this->hint('Regenerate the reader credentials from the RFID Readers page and update .env.');

            return;
        }

        $body = '{"probe":true}';
        $timestamp = (string) time();
        $nonce = 'doctor-probe';

        $matches = $signer->verify(
            $stored,
            $apiKey,
            $timestamp,
            $nonce,
            $body,
            $signer->sign($apiSecret, $apiKey, $timestamp, $nonce, $body),
        );

        $matches
            ? $this->reportPass('HMAC signing verified against the stored secret — signed requests will authenticate.')
            : $this->reportFail('RFID_LISTENER_API_SECRET does not match the secret stored for this reader.');
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    private function reportPass(string $message): void
    {
        $this->line("  <fg=green>OK</>   {$message}");
    }

    private function reportFail(string $message): void
    {
        $this->failures++;
        $this->line("  <fg=red>FAIL</> {$message}");
    }

    private function hint(string $message): void
    {
        $this->line("       <fg=gray>-> {$message}</>");
    }
}
