<?php

namespace Tests\Feature;

use App\Events\GateActivityRecorded;
use App\Models\RfidAssignment;
use App\Models\RfidDetection;
use App\Models\RfidReader;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Real-time push of gate activity to the dashboard over Reverb.
 *
 * The important test here is the last one: broadcasting is a convenience, and
 * the WebSocket server is a separate process that may be stopped. A gate
 * detection must still be recorded when it is.
 */
class GateActivityBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'broadcast-test-api-key';

    private const API_SECRET = 'broadcast-test-api-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCoreSettings();
    }

    private function makeReader(): RfidReader
    {
        return RfidReader::factory()
            ->withCredentials(self::API_KEY, self::API_SECRET)
            ->create(['status' => 'offline']);
    }

    /**
     * @return array{payload: array<string, mixed>, headers: array<string, string>}
     */
    private function signedDetection(string $epc): array
    {
        $payload = [
            'event_uuid' => (string) Str::uuid(),
            'epc' => $epc,
            'rssi' => -55,
            'antenna' => '0',
            'detected_at' => now()->format('Y-m-d H:i:s'),
        ];

        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $rawBody = json_encode($payload);

        return [
            'payload' => $payload,
            'headers' => [
                'X-Rfid-Api-Key' => self::API_KEY,
                'X-Rfid-Timestamp' => $timestamp,
                'X-Rfid-Nonce' => $nonce,
                'X-Rfid-Signature' => hash_hmac('sha256', implode('.', [self::API_KEY, $timestamp, $nonce, $rawBody]), self::API_SECRET),
            ],
        ];
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::where('slug', $slug)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'active']);
    }

    /**
     * phpunit.xml pins BROADCAST_CONNECTION to "null", whose broadcaster does
     * no channel authorization at all — every /broadcasting/auth request comes
     * back an empty 200, so these tests would pass while proving nothing.
     * Switch to the real (Pusher-protocol) broadcaster for the auth checks.
     */
    private function useRealBroadcaster(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app-id',
        ]);

        // Channel callbacks are registered against whichever driver was the
        // default when routes/channels.php loaded during boot — the "null" one.
        // Swapping the driver hands us a fresh instance with no channels at all,
        // which denies everything and would make these assertions meaningless in
        // the opposite direction. Re-run the file against the new driver.
        require base_path('routes/channels.php');
    }

    /**
     * @return TestResponse
     */
    private function authorizeChannel()
    {
        return $this->post('/broadcasting/auth', [
            'channel_name' => 'private-gate-activity',
            'socket_id' => '1234.5678',
        ]);
    }

    public function test_a_detection_broadcasts_gate_activity(): void
    {
        Event::fake([GateActivityRecorded::class]);

        $this->makeReader();
        $request = $this->signedDetection('E2806915000040281DB049BE9C91');

        $this->postJson('/api/rfid/detections', $request['payload'], $request['headers'])
            ->assertCreated();

        Event::assertDispatched(GateActivityRecorded::class, function (GateActivityRecorded $event) {
            return $event->detection->epc === 'E2806915000040281DB049BE9C91';
        });
    }

    public function test_the_event_broadcasts_on_a_private_channel_with_a_useful_payload(): void
    {
        $this->makeReader();

        $tag = RfidTag::factory()->create(['epc' => 'E2806915000040281DB049BE9C91', 'status' => 'active']);
        $vehicle = Vehicle::factory()->create(['plate_number' => 'BAF0812', 'current_state' => 'outside']);
        RfidAssignment::factory()->create([
            'rfid_tag_id' => $tag->id,
            'vehicle_id' => $vehicle->id,
            'visitor_visit_id' => null,
            'assigned_at' => now()->subDay(),
            'released_at' => null,
        ]);

        $captured = null;
        Event::listen(GateActivityRecorded::class, function ($event) use (&$captured) {
            $captured = $event;
        });

        $request = $this->signedDetection('E2806915000040281DB049BE9C91');
        $this->postJson('/api/rfid/detections', $request['payload'], $request['headers'])->assertCreated();

        $this->assertNotNull($captured);
        $this->assertSame('gate.activity', $captured->broadcastAs());

        $channels = $captured->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-gate-activity', (string) $channels[0]);

        $payload = $captured->broadcastWith();
        $this->assertSame('E2806915000040281DB049BE9C91', $payload['epc']);
        $this->assertSame('authorized', $payload['decision']);
        $this->assertSame('entry', $payload['direction']);
        $this->assertSame('BAF0812', $payload['plate_number']);
        $this->assertFalse($payload['is_duplicate']);
    }

    public function test_an_authenticated_user_can_join_the_gate_activity_channel(): void
    {
        $this->useRealBroadcaster();

        $this->actingAs($this->userWithRole('security-officer'));

        $response = $this->authorizeChannel();

        $response->assertOk();
        // A real authorization returns a signed token, not an empty body.
        $this->assertNotEmpty($response->getContent());
    }

    public function test_a_guest_cannot_join_the_gate_activity_channel(): void
    {
        $this->useRealBroadcaster();

        $this->authorizeChannel()->assertForbidden();
    }

    public function test_a_suspended_user_cannot_join_the_gate_activity_channel(): void
    {
        $this->useRealBroadcaster();

        $role = Role::where('slug', 'administrator')->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'suspended']);

        $this->actingAs($user);

        // Suspended beats administrator: the channel callback checks isActive()
        // before permissions, so a full-access account that has been locked out
        // cannot keep watching the gate over a socket.
        $this->authorizeChannel()->assertForbidden();
    }

    public function test_a_detection_is_still_recorded_when_broadcasting_fails(): void
    {
        // Stand in for the Reverb server being stopped.
        $this->app->bind(BroadcastFactory::class, function () {
            throw new RuntimeException('Reverb is not running.');
        });

        $this->makeReader();
        $request = $this->signedDetection('E2806915000040281DB049BE9C91');

        $this->postJson('/api/rfid/detections', $request['payload'], $request['headers'])
            ->assertCreated();

        $this->assertDatabaseHas('rfid_detections', [
            'event_uuid' => $request['payload']['event_uuid'],
            'epc' => 'E2806915000040281DB049BE9C91',
        ]);
        $this->assertSame(1, RfidDetection::count());
    }
}
