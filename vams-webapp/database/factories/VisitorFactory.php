<?php

namespace Database\Factories;

use App\Models\Visitor;
use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // visitor_code is auto-generated via model observer; omit here
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'contact_number' => fake()->numerify('09#########'),
            'valid_id_type' => fake()->randomElement(['Driver License', 'Passport', 'UMID', 'Company ID']),
            'valid_id_number' => strtoupper(fake()->bothify('??-####-####')),
            'address' => fake()->address(),
        ];
    }
}
