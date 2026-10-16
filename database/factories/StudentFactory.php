<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_number' => 'T'.fake()->unique()->numerify('######'),
            'full_name' => fake()->name(),
            'gender' => 'male',
            'enrollment_date' => now()->subMonth(),
            'status' => 'active',
            'current_circle_id' => null,
        ];
    }
}
