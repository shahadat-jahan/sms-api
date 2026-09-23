<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'roll' => 'ROLL-'.fake()->unique()->numerify('#####'),
            'class' => 'Class '.fake()->numberBetween(1, 12),
            'section' => fake()->randomElement(['A', 'B', 'C']),
            'phone' => fake()->numerify('017########'),
            'address' => fake()->address(),
        ];
    }
}
