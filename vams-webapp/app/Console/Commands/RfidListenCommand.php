<?php

namespace App\Console\Commands;

use App\Services\Rfid\DetectionForwarder;
use App\Services\Rfid\MmProtocolCodec;
use App\Services\Rfid\ReaderTransport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Windows RFID Listener / Device Service (context/TASKS.md, backlog item 2).
 *
 * Holds the TCP link to the S4A UHF-202415, decodes its MM-protocol tag
 * frames, and forwards each read to the ingestion API as an HMAC-signed POST.
 * It talks to the application only over that HTTP boundary — never to the
 * database directly — so the reader gets no more trust than any other device
 * (NETWORK_CONFIG.md "Communication Flow").
 *
 * Run it alongside `php artisan serve`:
 *
 *   php artisan rfid:listen
 *   php artisan rfid:listen --dry-run      # decode and print, post nothing
 *   php artisan rfid:listen --seconds=30   # stop after 30 seconds
 */
class RfidListenCommand extends Command
{
    protected $signature = 'rfid:listen
        {--dry-run : Decode and display tag reads without posting them to the API}
        {--seconds= : Stop after this many seconds instead of running indefinitely}';

    protected $description = 'Read tags from the RFID reader and forward them to the ingestion API';

    /**
     * Last forwarded time per EPC, for local read suppression.
     *
     * @var array<string, float>
     */
    private array $lastSeen = [];

    private bool $shouldStop = false;

    public function handle(MmProtocolCodec $codec, DetectionForwarder $forwarder): int
    {
        $config = config('rfid.listener');
        $dryRun = (bool) $this->option('dry-run');
        $deadline = $this->option('seconds') ? microtime(true) + (float) $this->option('seconds') : null;

        $transport = ReaderTransport::fromConfig();

        $this->listenForShutdownSignal();

        $this->components->info('VAMS RFID Listener');
        $this->line('  Reader:   '.$transport->endpoint());
        $this->line('  API:      '.($dryRun ? 'disabled (--dry-run)' : $config['api']['url']));
        $this->line('  Suppress: repeat reads of the same tag within '.$config['local_debounce_seconds'].'s');
        $this->line('  Stop with Ctrl+C.');
        $this->newLine();

        $buffer = '';
        $nextPoll = 0.0;

        while (! $this->shouldStop) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            try {
                if (! $transport->isConnected()) {
                    $transport->connect();
                    $this->line('  <fg=green>Connected</> to '.$transport->peer());

                    $buffer = '';
                    $nextPoll = 0.0;
                }

                if ($config['poll'] && microtime(true) >= $nextPoll) {
                    $transport->send($codec->inventoryCommand());
                    $nextPoll = microtime(true) + ($config['poll_interval_ms'] / 1000);
                }

                $buffer .= $transport->read(0.2);

                foreach ($codec->extractFrames($buffer) as $frame) {
                    $tag = $codec->decodeTagReport($frame);

                    if ($tag !== null) {
                        $this->handleTagRead($tag, $forwarder, $dryRun, (int) $config['local_debounce_seconds']);
                    }
                }
            } catch (Throwable $e) {
                $transport->disconnect();

                $this->components->error($e->getMessage());
                Log::warning('RFID listener: reader link problem.', ['error' => $e->getMessage()]);

                if ($deadline !== null && microtime(true) + (int) $config['reconnect_delay_seconds'] >= $deadline) {
                    break;
                }

                $this->line('  Retrying in '.$config['reconnect_delay_seconds'].'s...');
                sleep((int) $config['reconnect_delay_seconds']);
            }
        }

        $transport->close();
        $this->newLine();
        $this->components->info('Listener stopped.');

        return self::SUCCESS;
    }

    /**
     * Forward one decoded tag read, unless this EPC was already reported
     * inside the local suppression window.
     *
     * @param  array{antenna: int, pc: string, epc: string, rssi: int}  $tag
     */
    private function handleTagRead(array $tag, DetectionForwarder $forwarder, bool $dryRun, int $suppressSeconds): void
    {
        $epc = $tag['epc'];
        $now = microtime(true);

        if (isset($this->lastSeen[$epc]) && ($now - $this->lastSeen[$epc]) < $suppressSeconds) {
            return;
        }

        $this->lastSeen[$epc] = $now;

        $stamp = now()->format('H:i:s');
        $detail = "ant={$tag['antenna']} rssi={$tag['rssi']}dBm";

        if ($dryRun) {
            $this->line("  <fg=gray>{$stamp}</> <fg=cyan>{$epc}</>  {$detail}  <fg=yellow>(dry run, not posted)</>");

            return;
        }

        try {
            $result = $forwarder->forward($epc, $tag['rssi'], (string) $tag['antenna']);
        } catch (Throwable $e) {
            $this->line("  <fg=gray>{$stamp}</> <fg=cyan>{$epc}</>  <fg=red>forward failed: {$e->getMessage()}</>");
            Log::error('RFID listener: could not forward a detection.', ['epc' => $epc, 'error' => $e->getMessage()]);

            // Let the next read of this tag retry immediately.
            unset($this->lastSeen[$epc]);

            return;
        }

        $this->line("  <fg=gray>{$stamp}</> <fg=cyan>{$epc}</>  {$detail}  ".$this->describeOutcome($result));
    }

    /**
     * @param  array{status: int, body: array<string, mixed>}  $result
     */
    private function describeOutcome(array $result): string
    {
        if ($result['status'] !== 201) {
            $message = $result['body']['message'] ?? 'unexpected response';

            return "<fg=red>HTTP {$result['status']}: {$message}</>";
        }

        $body = $result['body'];

        if (! empty($body['is_duplicate'])) {
            return '<fg=gray>duplicate (debounced)</>';
        }

        if (($body['decision'] ?? null) === 'authorized') {
            return '<fg=green>AUTHORIZED</> <fg=gray>('.($body['direction'] ?? '?').')</>';
        }

        return '<fg=red>DENIED</> <fg=gray>('.($body['denial_reason'] ?? 'unknown').')</>';
    }

    /**
     * Stop cleanly on Ctrl+C where the platform supports it. Windows CLI PHP
     * has no pcntl, so there the process is simply interrupted — the transport
     * is torn down by the OS and nothing is left half-written.
     */
    private function listenForShutdownSignal(): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () {
                $this->shouldStop = true;
            });
        }
    }
}
