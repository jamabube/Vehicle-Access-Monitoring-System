<?php

namespace App\Services\Rfid;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The device-service half of the RFID ingestion contract: turns one decoded
 * tag read into an HMAC-signed POST to `/api/rfid/detections`.
 *
 * It deliberately signs and sends the *same* raw JSON string, because
 * VerifyRfidSignature verifies the signature against the raw request body —
 * re-encoding the payload between signing and sending would invalidate it.
 *
 * Credentials come from config('rfid.listener.api'), i.e. from .env, and are
 * never logged; see context/RULES.md §5.
 */
class DetectionForwarder
{
    public function __construct(private readonly HmacSignatureVerifier $signer) {}

    /**
     * Forward a single detection.
     *
     * @return array{status: int, event_uuid: string, body: array<string, mixed>}
     *
     * @throws RuntimeException when credentials are missing or the API is unreachable.
     */
    public function forward(string $epc, ?int $rssi = null, ?string $antenna = null, ?Carbon $detectedAt = null): array
    {
        $config = config('rfid.listener.api');

        $apiKey = $config['key'];
        $apiSecret = $config['secret'];

        if (! $apiKey || ! $apiSecret) {
            throw new RuntimeException(
                'Missing listener credentials. Set RFID_LISTENER_API_KEY and RFID_LISTENER_API_SECRET in .env '
                .'(generate them with: php artisan db:seed --class=RfidReaderSeeder).'
            );
        }

        $eventUuid = (string) Str::uuid();
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();

        // json_encode here, and send this exact string — see the class docblock.
        $body = json_encode([
            'event_uuid' => $eventUuid,
            'epc' => $epc,
            'rssi' => $rssi,
            'antenna' => $antenna,
            'detected_at' => ($detectedAt ?? now())->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR);

        try {
            $response = Http::withHeaders([
                'X-Rfid-Api-Key' => $apiKey,
                'X-Rfid-Timestamp' => $timestamp,
                'X-Rfid-Nonce' => $nonce,
                'X-Rfid-Signature' => $this->signer->sign($apiSecret, $apiKey, $timestamp, $nonce, $body),
            ])
                ->withBody($body, 'application/json')
                ->withOptions(['verify' => (bool) $config['verify_tls']])
                ->timeout((int) $config['timeout_seconds'])
                ->post($config['url']);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "Cannot reach the ingestion API at {$config['url']} — is `php artisan serve` running? ({$e->getMessage()})",
                previous: $e
            );
        }

        return [
            'status' => $response->status(),
            'event_uuid' => $eventUuid,
            'body' => (array) $response->json(),
        ];
    }
}
