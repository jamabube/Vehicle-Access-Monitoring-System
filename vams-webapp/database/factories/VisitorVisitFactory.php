<?php

namespace Database\Factories;

use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitorVisit>
 */
class VisitorVisitFactory extends Factory
{
    protected $model = VisitorVisit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // visit_code is auto-generated via model observer; omit here
            'visitor_id' => Visitor::factory(),
            'vehicle_id' => null,
            'rfid_tag_id' => null,
            'purpose' => fake()->randomElement(['Delivery', 'Meeting', 'Family visit', 'Maintenance']),
            'host_name' => fake()->name(),
            'valid_from' => now(),
            'valid_until' => now()->addHours(8),
            'status' => 'active',
            'checked_out_at' => null,
            'registered_by' => null,
        ];
    }
}
