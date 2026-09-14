<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestNonce;
use App\Models\RfidReader;
use App\Models\SystemLog;
use App\Services\Rfid\HmacSignatureVerifier;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates every RFID device-service request per context/RULES.md §5:
 * HMAC signature + timestamp tolerance + single-use nonce.
 *
 * Expected request headers (all required):
 *   X-Rfid-Api-Key:   the reader's rfid_readers.api_key
 *   X-Rfid-Timestamp: Unix timestamp (seconds) the request was signed at
 *   X-Rfid-Nonce:     a request-unique random string
 *   X-Rfid-Signature: hex hash_hmac('sha256', "{api_key}.{timestamp}.{nonce}.{raw_body}", secret)
 *
 * On success, the resolved RfidReader is attached to the request attributes
 * under the 'rfidReader' key for the controller to consume.
 */
class VerifyRfidSignature
{
    public function __construct(private readonly HmacSignatureVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Rfid-Api-Key');
        $timestamp = $request->header('X-Rfid-Timestamp');
        $nonce = $request->header('X-Rfid-Nonce');
        $signature = $request->header('X-Rfid-Signature');

        if (! $apiKey || ! $timestamp || ! $nonce || ! $signature) {
            return $this->reject(null, 'Missing required RFID authentication headers.');
        }

        if (! ctype_digit((string) $timestamp)) {
            return $this->reject(null, 'Invalid timestamp header.');
        }

        $tolerance = (int) config('rfid.hmac_tolerance_seconds');
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return $this->reject(null, 'Request timestamp is outside the allowed tolerance window.', ['api_key' => $apiKey]);
        }

        $reader = RfidReader::where('api_key', $apiKey)->first();

        if (! $reader || $reader->status === 'disabled') {
            return $this->reject(null, 'Unknown or disabled RFID reader credentials.', ['api_key' => $apiKey]);
        }

        try {
            $secret = Crypt::decryptString($reader->api_secret_hash);
        } catch (DecryptException) {
            return $this->reject($reader->id, 'Unable to verify RFID reader credentials.');
        }

        $rawBody = $request->getContent();

        if (! $this->verifier->verify($secret, $apiKey, (string) $timestamp, $nonce, $rawBody, $signature)) {
            return $this->reject($reader->id, 'Invalid request signature.');
        }

        if (! $this->consumeNonce($reader, $nonce)) {
            $this->log($reader->id, 'Replayed nonce rejected.', ['nonce' => $nonce]);

            return response()->json(['message' => 'This request has already been processed (replayed nonce).'], 409);
        }

        $reader->forceFill(['status' => 'online', 'last_heartbeat_at' => now()])->save();

        $request->attributes->set('rfidReader', $reader);

        return $next($request);
    }

    /**
     * Record the nonce as used, rejecting the request if it is a replay of a
     * still-valid (not-yet-expired) nonce for this reader.
     */
    private function consumeNonce(RfidReader $reader, string $nonce): bool
    {
        $ttl = (int) config('rfid.nonce_ttl_seconds');

        $existing = ApiRequestNonce::where('rfid_reader_id', $reader->id)
            ->where('nonce', $nonce)
            ->first();

        if ($existing) {
            if ($existing->expires_at->isFuture()) {
                // The nonce is still within its active TTL window — this is a
                // genuine replay attempt; reject it.
                return false;
            }

            // The nonce record exists but its TTL has already elapsed.
            // Delete the stale row so that the fresh insert below creates a
            // clean, active record.  Without this deletion the old record
            // would be recycled (its expiry re-stamped) rather than replaced,
            // which means the nonce slot would never truly "close" and a
            // delayed replay would be silently accepted.
            $existing->delete();
        }

        ApiRequestNonce::create([
            'rfid_reader_id' => $reader->id,
            'nonce'          => $nonce,
            'expires_at'     => now()->addSeconds($ttl),
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function reject(?int $rfidReaderId, string $message, array $context = []): Response
    {
        $this->log($rfidReaderId, $message, $context);

        return response()->json(['message' => $message], 401);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(?int $rfidReaderId, string $message, array $context = []): void
    {
        SystemLog::create([
            'level' => 'warning',
            'source' => 'rfid_ingestion_api',
            'rfid_reader_id' => $rfidReaderId,
            'message' => $message,
            'context' => $context ?: null,
        ]);
    }
}
