<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // vehicle_code is auto-generated via model observer; omit here
            'plate_number' => strtoupper(fake()->unique()->bothify('???-####')),
            // Draw from the canonical list so generated records match what the
            // form can actually produce.
            'vehicle_type' => fake()->randomElement(Vehicle::TYPES),
            'make' => fake()->randomElement(['Toyota', 'Honda', 'Mitsubishi', 'Ford']),
            'model' => fake()->word(),
            'color' => fake()->safeColorName(),
            'owner_type' => 'employee',
            'employee_id' => null,
            'status' => 'active',
            'current_state' => 'outside',
        ];
    }
}
