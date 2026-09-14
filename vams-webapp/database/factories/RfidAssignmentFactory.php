<?php

namespace Database\Factories;

use App\Models\RfidAssignment;
use App\Models\RfidTag;
use App\Models\Vehicle;
use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfidAssignment>
 */
class RfidAssignmentFactory extends Factory
{
    protected $model = RfidAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // assignment_code is auto-generated via model observer; omit here
            'rfid_tag_id' => RfidTag::factory(),
            'vehicle_id' => Vehicle::factory(),
            'visitor_visit_id' => null,
            'assigned_at' => now(),
            'released_at' => null,
            'assigned_by' => null,
        ];
    }
}
