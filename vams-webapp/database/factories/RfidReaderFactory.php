<?php

namespace Database\Factories;

use App\Models\RfidReader;
use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @extends Factory<RfidReader>
 */
class RfidReaderFactory extends Factory
{
    protected $model = RfidReader::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_name' => fake()->unique()->words(2, true).' Reader',
            // device_code is auto-generated via model observer; omit here
            'model' => 'S4A UHF-202415',
            'location' => fake()->randomElement(['Main Gate', 'Employee Gate', 'Visitor Gate']),
            'ip_address' => fake()->localIpv4(),
            'api_key' => Str::random(40),
            'api_secret_hash' => Crypt::encryptString(Str::random(64)),
            'status' => 'offline',
            'last_heartbeat_at' => null,
        ];
    }

    /**
     * Set a known plaintext API key/secret pair (encrypted at rest), so tests
     * can compute a valid HMAC signature against a reader's real credentials.
     */
    public function withCredentials(string $apiKey, string $apiSecret): static
    {
        return $this->state(fn (array $attributes) => [
            'api_key' => $apiKey,
            'api_secret_hash' => Crypt::encryptString($apiSecret),
        ]);
    }
}
