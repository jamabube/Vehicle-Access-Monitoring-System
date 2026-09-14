<?php

namespace Database\Factories;

use App\Models\AccessLog;
use App\Models\RfidDetection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessLog>
 */
class AccessLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfid_detection_id' => RfidDetection::factory(),
            'rfid_tag_id' => null,
            'vehicle_id' => null,
            'visitor_visit_id' => null,
            'direction' => $this->faker->randomElement(['entry', 'exit', null]),
            'decision' => $this->faker->randomElement(['authorized', 'denied']),
            'denial_reason' => null,
            'occurred_at' => now(),
        ];
    }
}
