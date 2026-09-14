<?php

namespace Database\Factories;

use App\Models\RfidTag;
use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfidTag>
 */
class RfidTagFactory extends Factory
{
    protected $model = RfidTag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // tag_code is auto-generated via model observer; omit here
            'epc' => strtoupper(fake()->unique()->bothify('E280########################')),
            'credential_type' => fake()->randomElement(['sticker', 'card']),
            'status' => 'active',
            'issued_at' => now(),
            'expires_at' => null,
            'notes' => null,
        ];
    }
}
