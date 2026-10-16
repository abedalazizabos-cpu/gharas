<?php

namespace Database\Factories;

use App\Models\Circle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Circle>
 */
class CircleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'حلقة '.fake()->unique()->firstName(),
            'code' => 'C-'.fake()->unique()->numberBetween(100, 999),
            'teacher_id' => null,
            'level' => 'مبتدئ',
            'days' => \App\Models\Circle::STUDY_DAYS,
            'start_time' => '16:00',
            'end_time' => '17:30',
            'capacity' => 15,
            'status' => 'active',
            'is_public' => true,
        ];
    }
}
