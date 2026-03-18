<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => strtoupper(fake()->unique()->bothify('SUBJ###')),
            'name' => ucwords($name),
            'short_name' => strtoupper(fake()->lexify('???')),
            'is_core' => true,
            'is_active' => true,
        ];
    }
}
