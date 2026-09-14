<?php

namespace Tests\Feature;

use App\Models\ApiRequestNonce;
use App\Models\RfidAssignment;
use App\Models\RfidDetection;
use App\Models\RfidReader;
use App\Models\RfidTag;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RfidIngestionApiTest extends TestCase
{
    use RefreshDatabase;

    protected const API_KEY = 'test-reader-api-key';

    protected const API_SECRET = 'test-reader-api-secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Seed code settings required by RfidReader factory's device_code generation.
        // This fixes the 9 pre-existing test failures documented in context/TASKS.md line 88.
        $this->seedCoreSettings();
    }

    protected function makeReader(array $attributes = []): RfidReader
    {
        return RfidReader::factory()
            ->withCredentials(self::API_KEY, self::API_SECRET)
            ->create([...['status' => 'offline'], ...$attributes]);
    }

    /**
     * Build a signed JSON request payload + matching auth headers.
     *
     * @param  array<string, mixed>  $payload
     * @return array{payload: array<string, mixed>, headers: array<string, string>}
     */
    protected function signedRequest(array $payload, ?string $secret = null, ?string $timestamp = null, ?string $nonce = null): array
    {
        $secret ??= self::API_SECRET;
        $timestamp ??= (string) time();
        $nonce ??= (string) Str::uuid();
        $rawBody = json_encode($payload);

        $signature = hash_hmac('sha256', implode('.', [self::API_KEY, $timestamp, $nonce, $rawBody]), $secret);

        return [
            'payload' => $payload,
            'headers' => [
                'X-Rfid-Api-Key' => self::API_KEY,
                'X-Rfid-Timestamp' => $timestamp,
                'X-Rfid-Nonce' => $nonce,
                'X-Rfid-Signature' => $signature,
            ],
        ];
    }

    protected function detectionPayload(string $epc, array $overrides = []): array
    {
        return [
            'event_uuid' => (string) Str::uuid(),
            'epc' => $epc,
            'rssi' => -42,
            'antenna' => '1',
            'detected_at' => now()->format('Y-m-d H:i:s'),
            ...$overrides,
        ];
    }

    public function test_valid_signed_request_with_unknown_epc_is_denied_but_recorded(): void
    {
        $this->makeReader();

        $request = $this->signedRequest($this->detectionPayload('UNKNOWN-EPC-1'));

        $response = $this->postJson('/api/rfid/detections', $request['payload'], $request['headers']);

        $response->assertCreated();
        $response->assertJson([
            'is_duplicate' => false,
            'decision' => 'denied',
            'denial_reason' => 'unknown_credential',
        ]);

        $this->assertDatabaseHas('rfid_detections', ['epc' => 'UNKNOWN-EPC-1', 'is_duplicate' => false]);
        $this->assertDatabaseHas('access_logs', ['decision' => 'denied', 'denial_reason' => 'unknown_credential']);
    }

    public function test_request_with_invalid_signature_is_rejected(): void
    {
        $this->makeReader();

        $payload = $this->detectionPayload('SOME-EPC');
        $request = $this->signedRequest($payload, secret: 'wrong-secret');

        $response = $this->postJson('/api/rfid/detections', $payload, $request['headers']);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('rfid_detections', ['epc' => 'SOME-EPC']);
    }

    public function test_request_with_stale_timestamp_is_rejected(): void
    {
        $this->makeReader();

        $payload = $this->detectionPayload('SOME-EPC');
        $staleTimestamp = (string) (time() - 3600);
        $request = $this->signedRequest($payload, timestamp: $staleTimestamp);

        $response = $this->postJson('/api/rfid/detections', $payload, $request['headers']);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('rfid_detections', ['epc' => 'SOME-EPC']);
    }

    public function test_replayed_nonce_is_rejected(): void
    {
        $this->makeReader();

        $timestamp = (string) time();
        $nonce = (string) Str::uuid();

        $firstPayload = $this->detectionPayload('SOME-EPC-1');
        $firstRequest = $this->signedRequest($firstPayload, timestamp: $timestamp, nonce: $nonce);
        $this->postJson('/api/rfid/detections', $firstRequest['payload'], $firstRequest['headers'])->assertCreated();

        // Reuse the exact same nonce (+ timestamp) with a different payload/signature.
        $secondPayload = $this->detectionPayload('SOME-EPC-2');
        $secondRequest = $this->signedRequest($secondPayload, timestamp: $timestamp, nonce: $nonce);

        $response = $this->postJson('/api/rfid/detections', $secondRequest['payload'], $secondRequest['headers']);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('rfid_detections', ['epc' => 'SOME-EPC-2']);
        $this->assertSame(1, ApiRequestNonce::where('nonce', $nonce)->count());
    }

    public function test_unknown_api_key_is_rejected(): void
    {
        $this->makeReader();

        $payload = $this->detectionPayload('SOME-EPC');
        $rawBody = json_encode($payload);
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $signature = hash_hmac('sha256', implode('.', ['bogus-api-key', $timestamp, $nonce, $rawBody]), self::API_SECRET);

        $response = $this->postJson('/api/rfid/detections', $payload, [
            'X-Rfid-Api-Key' => 'bogus-api-key',
            'X-Rfid-Timestamp' => $timestamp,
            'X-Rfid-Nonce' => $nonce,
            'X-Rfid-Signature' => $signature,
        ]);

        $response->assertStatus(401);
    }

    public function test_missing_authentication_headers_are_rejected(): void
    {
        $this->makeReader();

        $response = $this->postJson('/api/rfid/detections', $this->detectionPayload('SOME-EPC'));

        $response->assertStatus(401);
    }

    public function test_disabled_reader_is_rejected(): void
    {
        $this->makeReader(['status' => 'disabled']);

        $request = $this->signedRequest($this->detectionPayload('SOME-EPC'));
        $response = $this->postJson('/api/rfid/detections', $request['payload'], $request['headers']);

        $response->assertStatus(401);
    }

    public function test_duplicate_detection_within_debounce_window_is_flagged_and_not_double_processed(): void
    {
        $this->makeReader();

        $tag = RfidTag::factory()->create(['status' => 'active', 'credential_type' => 'sticker']);
        $vehicle = Vehicle::factory()->create(['status' => 'active', 'current_state' => 'outside']);
        RfidAssignment::factory()->create(['rfid_tag_id' => $tag->id, 'vehicle_id' => $vehicle->id, 'released_at' => null]);

        $baseTime = now();

        $firstRequest = $this->signedRequest($this->detectionPayload($tag->epc, [
            'detected_at' => $baseTime->format('Y-m-d H:i:s'),
        ]));
        $this->postJson('/api/rfid/detections', $firstRequest['payload'], $firstRequest['headers'])->assertCreated();

        $secondRequest = $this->signedRequest($this->detectionPayload($tag->epc, [
            'detected_at' => $baseTime->clone()->addSeconds(2)->format('Y-m-d H:i:s'),
        ]));
        $secondResponse = $this->postJson('/api/rfid/detections', $secondRequest['payload'], $secondRequest['headers']);

        $secondResponse->assertCreated();
        $secondResponse->assertJson(['is_duplicate' => true, 'decision' => null]);

        $this->assertSame(2, RfidDetection::where('epc', $tag->epc)->count());
        $this->assertSame(1, RfidDetection::where('epc', $tag->epc)->where('is_duplicate', true)->count());
        $this->assertDatabaseCount('access_logs', 1);
    }

    // -------------------------------------------------------------------------
    // REGRESSION TESTS — Security Findings
    // -------------------------------------------------------------------------

    /**
     * REGRESSION: FINDING-04 — Nonce Expiry Logic Flaw.
     *
     * An expired nonce record must NOT be recycled (re-stamped and blindly
     * accepted for the same nonce value on a new request). After the TTL
     * window elapses the stale row should be deleted so that a legitimately
     * re-used nonce string in a new, correctly-signed request is accepted —
     * and a subsequent immediate replay of that same new request is rejected
     * because the freshly-inserted nonce record is now active (isFuture()).
     *
     * Pre-fix behaviour (BUG):
     *   $existing->update(['expires_at' => ...]) re-stamps the old row;
     *   the method returns true regardless — the nonce is accepted forever.
     *
     * Post-fix behaviour (CORRECT):
     *   $existing->delete(); followed by a fresh ApiRequestNonce::create().
     *   The DB ends up with exactly one record whose expires_at is in the
     *   future, and a second request with the same nonce is rejected 409.
     */
    public function test_expired_nonce_is_freed_and_fresh_request_is_accepted(): void
    {
        $reader = $this->makeReader();

        $nonce = (string) Str::uuid();

        // Pre-seed an already-expired nonce record for this reader + nonce pair.
        ApiRequestNonce::create([
            'rfid_reader_id' => $reader->id,
            'nonce'          => $nonce,
            'expires_at'     => now()->subMinutes(20), // well past any TTL
        ]);

        // A new, legitimately signed request carrying the same nonce string
        // should succeed — the TTL window has elapsed so it no longer counts
        // as an active replay.
        $request = $this->signedRequest($this->detectionPayload('EXPIRE-TEST-EPC'), nonce: $nonce);
        $response = $this->postJson('/api/rfid/detections', $request['payload'], $request['headers']);

        $response->assertCreated();

        // After the fix: exactly one nonce record exists for this reader + nonce,
        // with a *future* expires_at (the stale row was deleted and replaced).
        $this->assertDatabaseCount('api_request_nonces', 1);
        $this->assertTrue(
            ApiRequestNonce::where('rfid_reader_id', $reader->id)
                ->where('nonce', $nonce)
                ->where('expires_at', '>', now())
                ->exists(),
            'The nonce record should have been replaced with a fresh, future-dated entry after expiry.'
        );
    }

    public function test_expired_nonce_replaced_by_fresh_record_then_immediate_replay_is_rejected(): void
    {
        $reader = $this->makeReader();

        $nonce = (string) Str::uuid();

        // Pre-seed an expired nonce so the first request triggers the
        // delete-and-replace path.
        ApiRequestNonce::create([
            'rfid_reader_id' => $reader->id,
            'nonce'          => $nonce,
            'expires_at'     => now()->subMinutes(20),
        ]);

        // First request: accepted — expired nonce was freed, fresh record created.
        $firstRequest = $this->signedRequest(
            $this->detectionPayload('REPLAY-RECYCLE-EPC-1'),
            nonce: $nonce
        );
        $this->postJson('/api/rfid/detections', $firstRequest['payload'], $firstRequest['headers'])
            ->assertCreated();

        // Second request: a replay using the same nonce on a different payload.
        // Must be rejected 409 because the nonce record is now *active* (isFuture() == true).
        $secondRequest = $this->signedRequest(
            $this->detectionPayload('REPLAY-RECYCLE-EPC-2'),
            nonce: $nonce
        );
        $response = $this->postJson('/api/rfid/detections', $secondRequest['payload'], $secondRequest['headers']);

        $response->assertStatus(409);
        $response->assertJson(['message' => 'This request has already been processed (replayed nonce).']);

        // Only the first detection should exist — the replay was blocked.
        $this->assertDatabaseHas('rfid_detections', ['epc' => 'REPLAY-RECYCLE-EPC-1']);
        $this->assertDatabaseMissing('rfid_detections', ['epc' => 'REPLAY-RECYCLE-EPC-2']);
    }

    // -------------------------------------------------------------------------

    public function test_valid_sticker_assignment_authorizes_vehicle_entry_and_toggles_state(): void
    {
        $this->makeReader();

        $tag = RfidTag::factory()->create(['status' => 'active', 'credential_type' => 'sticker']);
        $vehicle = Vehicle::factory()->create(['status' => 'active', 'current_state' => 'outside']);
        RfidAssignment::factory()->create(['rfid_tag_id' => $tag->id, 'vehicle_id' => $vehicle->id, 'released_at' => null]);

        $request = $this->signedRequest($this->detectionPayload($tag->epc));
        $response = $this->postJson('/api/rfid/detections', $request['payload'], $request['headers']);

        $response->assertCreated();
        $response->assertJson([
            'decision' => 'authorized',
            'direction' => 'entry',
        ]);

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'current_state' => 'inside']);
        $this->assertDatabaseHas('access_logs', [
            'decision' => 'authorized',
            'direction' => 'entry',
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
