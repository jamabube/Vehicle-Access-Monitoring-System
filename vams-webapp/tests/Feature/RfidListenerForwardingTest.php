<?php

namespace Tests\Feature;

use App\Models\RfidAssignment;
use App\Models\RfidDetection;
use App\Models\RfidReader;
use App\Models\RfidTag;
use App\Models\Vehicle;
use App\Services\Rfid\DetectionForwarder;
use App\Services\Rfid\HmacSignatureVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The listener signs its own requests, so the only failure that matters is a
 * disagreement between DetectionForwarder (the device side) and
 * VerifyRfidSignature (the server side). These tests wire the two together:
 * requests the forwarder produces are replayed against the real API route, so
 * a drift in either half breaks the build rather than the gate.
 */
class RfidListenerForwardingTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'listener-test-api-key';

    private const API_SECRET = 'listener-test-api-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCoreSettings();

        config()->set('rfid.listener.api.key', self::API_KEY);
        config()->set('rfid.listener.api.secret', self::API_SECRET);
        config()->set('rfid.listener.api.url', 'http://vams.test/api/rfid/detections');
    }

    private function forwarder(): DetectionForwarder
    {
        return new DetectionForwarder(new HmacSignatureVerifier);
    }

    private function makeReader(array $attributes = []): RfidReader
    {
        return RfidReader::factory()
            ->withCredentials(self::API_KEY, self::API_SECRET)
            ->create([...['status' => 'offline'], ...$attributes]);
    }

    /**
     * Capture whatever the forwarder sends and replay it against the real
     * route, so the signature is verified by the actual middleware.
     */
    private function replayThroughApi(): void
    {
        Http::fake(function (Request $request) {
            $response = $this->call(
                'POST',
                '/api/rfid/detections',
                [],
                [],
                [],
                collect($request->headers())
                    ->mapWithKeys(fn ($values, $name) => ['HTTP_'.str_replace('-', '_', strtoupper($name)) => $values[0]])
                    ->all() + ['CONTENT_TYPE' => 'application/json'],
                $request->body(),
            );

            return Http::response($response->getContent(), $response->getStatusCode());
        });
    }

    public function test_a_forwarded_read_is_accepted_and_recorded_by_the_api(): void
    {
        $this->makeReader();
        $this->replayThroughApi();

        $result = $this->forwarder()->forward('E2003411B802011383258566', -55, '0');

        $this->assertSame(201, $result['status']);
        $this->assertSame('denied', $result['body']['decision']);
        $this->assertSame('unknown_credential', $result['body']['denial_reason']);

        $this->assertDatabaseHas('rfid_detections', [
            'event_uuid' => $result['event_uuid'],
            'epc' => 'E2003411B802011383258566',
            'rssi' => -55,
            'antenna' => '0',
            'is_duplicate' => false,
        ]);
    }

    public function test_a_forwarded_read_of_a_registered_tag_is_authorized(): void
    {
        $this->makeReader();
        $this->replayThroughApi();

        $tag = RfidTag::factory()->create(['epc' => 'E2003411B802011383258566', 'status' => 'active']);
        $vehicle = Vehicle::factory()->create(['current_state' => 'outside']);
        RfidAssignment::factory()->create([
            'rfid_tag_id' => $tag->id,
            'vehicle_id' => $vehicle->id,
            'visitor_visit_id' => null,
            'assigned_at' => now()->subDay(),
            'released_at' => null,
        ]);

        $result = $this->forwarder()->forward('E2003411B802011383258566', -55, '0');

        $this->assertSame(201, $result['status']);
        $this->assertSame('authorized', $result['body']['decision']);
        $this->assertSame('entry', $result['body']['direction']);
        $this->assertSame('inside', $vehicle->fresh()->current_state);
    }

    public function test_each_forwarded_read_uses_a_fresh_nonce_and_event_uuid(): void
    {
        $this->makeReader();
        $this->replayThroughApi();

        $first = $this->forwarder()->forward('E2003411B802011383258566', -55, '0');
        $second = $this->forwarder()->forward('E2003411B802011383258566', -55, '0');

        // Both are accepted (no 409 replay rejection) and stored separately.
        $this->assertSame(201, $first['status']);
        $this->assertSame(201, $second['status']);
        $this->assertNotSame($first['event_uuid'], $second['event_uuid']);
        $this->assertSame(2, RfidDetection::count());

        // The second read lands inside the debounce window, so it is flagged
        // rather than processed into a second access decision.
        $this->assertTrue((bool) $second['body']['is_duplicate']);
    }

    public function test_a_mismatched_secret_is_rejected_by_the_api(): void
    {
        $this->makeReader();
        $this->replayThroughApi();

        config()->set('rfid.listener.api.secret', 'the-wrong-secret');

        $result = $this->forwarder()->forward('E2003411B802011383258566', -55, '0');

        $this->assertSame(401, $result['status']);
        $this->assertDatabaseCount('rfid_detections', 0);
    }

    public function test_it_refuses_to_forward_without_configured_credentials(): void
    {
        config()->set('rfid.listener.api.key', null);

        $this->expectExceptionMessage('Missing listener credentials');

        $this->forwarder()->forward('E2003411B802011383258566');
    }
}
