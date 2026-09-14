<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // employee_code is auto-generated via model observer; omit here
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'department' => fake()->randomElement(['Operations', 'Security', 'Administration', 'IT']),
            'position' => fake()->jobTitle(),
            'contact_number' => fake()->numerify('09#########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
        ];
    }
}
