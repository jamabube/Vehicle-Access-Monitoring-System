<?php

namespace Tests\Feature;

use App\Models\RfidDetection;
use App\Models\RfidReader;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rate limiting on the sensitive endpoints (manuscript §1.2.2 objective 8 and
 * the Security Design table: "Rate Limiting — reduces excessive request traffic
 * and flooding risk").
 *
 * The limiter is keyed per *device*, not globally, so these tests also pin down
 * that one flooding reader cannot take the gate offline for the others.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'ratelimit-test-api-key';

    private const API_SECRET = 'ratelimit-test-api-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCoreSettings();
        RateLimiter::clear('rfid-ingestion');
    }

    private function makeReader(string $apiKey = self::API_KEY): RfidReader
    {
        return RfidReader::factory()
            ->withCredentials($apiKey, self::API_SECRET)
            ->create(['status' => 'offline']);
    }

    /**
     * @return array{payload: array<string, mixed>, headers: array<string, string>}
     */
    private function signedDetection(string $apiKey = self::API_KEY): array
    {
        $payload = [
            'event_uuid' => (string) Str::uuid(),
            'epc' => 'E2806915000040281DB049BE9C91',
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
                'X-Rfid-Api-Key' => $apiKey,
                'X-Rfid-Timestamp' => $timestamp,
                'X-Rfid-Nonce' => $nonce,
                'X-Rfid-Signature' => hash_hmac('sha256', implode('.', [$apiKey, $timestamp, $nonce, $rawBody]), self::API_SECRET),
            ],
        ];
    }

    public function test_detections_within_the_limit_are_accepted(): void
    {
        config(['rfid.rate_limit_per_minute' => 5]);
        $this->makeReader();

        for ($i = 0; $i < 5; $i++) {
            $request = $this->signedDetection();
            $this->postJson('/api/rfid/detections', $request['payload'], $request['headers'])
                ->assertCreated();
        }

        $this->assertSame(5, RfidDetection::count());
    }

    public function test_a_flood_of_detections_is_refused_with_429(): void
    {
        config(['rfid.rate_limit_per_minute' => 3]);
        $this->makeReader();

        for ($i = 0; $i < 3; $i++) {
            $request = $this->signedDetection();
            $this->postJson('/api/rfid/detections', $request['payload'], $request['headers'])->assertCreated();
        }

        $request = $this->signedDetection();
        $response = $this->postJson('/api/rfid/detections', $request['payload'], $request['headers']);

        $response->assertStatus(429);
        $response->assertJson(['message' => 'Too many detections submitted. Slow down and retry shortly.']);

        // The refused request must not have been stored.
        $this->assertSame(3, RfidDetection::count());
    }

    public function test_a_throttled_device_is_recorded_in_the_system_log(): void
    {
        config(['rfid.rate_limit_per_minute' => 1]);
        $this->makeReader();

        $first = $this->signedDetection();
        $this->postJson('/api/rfid/detections', $first['payload'], $first['headers'])->assertCreated();

        $second = $this->signedDetection();
        $this->postJson('/api/rfid/detections', $second['payload'], $second['headers'])->assertStatus(429);

        $log = SystemLog::where('source', 'rfid_ingestion_api')
            ->where('message', 'like', '%Rate limit exceeded%')
            ->first();

        $this->assertNotNull($log, 'A throttled device should leave a security event behind.');
        $this->assertSame('warning', $log->level);
        $this->assertSame(self::API_KEY, $log->context['api_key']);
    }

    public function test_one_flooding_reader_does_not_block_another(): void
    {
        config(['rfid.rate_limit_per_minute' => 2]);
        $this->makeReader();
        $this->makeReader('second-reader-api-key');

        // Exhaust the first reader's budget.
        for ($i = 0; $i < 3; $i++) {
            $request = $this->signedDetection();
            $this->postJson('/api/rfid/detections', $request['payload'], $request['headers']);
        }

        $flooded = $this->signedDetection();
        $this->postJson('/api/rfid/detections', $flooded['payload'], $flooded['headers'])->assertStatus(429);

        // The second gate must still work.
        $other = $this->signedDetection('second-reader-api-key');
        $this->postJson('/api/rfid/detections', $other['payload'], $other['headers'])->assertCreated();
    }

    public function test_the_limiter_runs_before_signature_verification(): void
    {
        // Junk with no valid credentials should be shed by the limiter rather
        // than costing a decrypt + HMAC comparison on every request.
        config(['rfid.rate_limit_per_minute' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/rfid/detections', [], ['X-Rfid-Api-Key' => 'bogus'])->assertStatus(401);
        }

        $this->postJson('/api/rfid/detections', [], ['X-Rfid-Api-Key' => 'bogus'])->assertStatus(429);
    }

    public function test_repeated_login_attempts_are_throttled(): void
    {
        RateLimiter::clear('login');

        User::factory()->create(['email' => 'target@vams.test', 'status' => 'active']);

        // The limiter allows 10 a minute; the 11th is refused outright.
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'target@vams.test', 'password' => 'wrong-password']);
        }

        $this->postJson('/login', ['email' => 'target@vams.test', 'password' => 'wrong-password'])
            ->assertStatus(429);

        $this->assertDatabaseHas('system_logs', [
            'source' => 'authentication',
            'level' => 'warning',
        ]);
    }
}
