<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Enums\LearnerSex;
use App\Models\Learner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Learner>
 */
class LearnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lrn' => fake()->unique()->numerify('############'),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->boolean(60) ? fake()->lastName() : null,
            'suffix' => fake()->boolean(10) ? fake()->randomElement(['Jr.', 'III']) : null,
            'sex' => fake()->randomElement(LearnerSex::cases()),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years'),
            'enrollment_status' => EnrollmentStatus::Active,
            'transfer_effective_date' => null,
        ];
    }
}
