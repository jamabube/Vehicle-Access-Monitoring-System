<?php

namespace Database\Factories;

use App\Models\RfidDetection;
use App\Models\RfidReader;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RfidDetection>
 */
class RfidDetectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_uuid' => (string) Str::uuid(),
            'rfid_reader_id' => RfidReader::factory(),
            'epc' => 'E200'.strtoupper(Str::random(21)),
            'rfid_tag_id' => null,
            'rssi' => $this->faker->numberBetween(-80, -20),
            'antenna' => (string) $this->faker->numberBetween(1, 4),
            'detected_at' => now(),
            'received_at' => now(),
            'is_duplicate' => false,
        ];
    }
}
