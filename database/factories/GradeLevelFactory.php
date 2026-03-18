<?php

namespace Database\Factories;

use App\Models\GradeLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeLevel>
 */
class GradeLevelFactory extends Factory
{
    public function definition(): array
    {
        $level = fake()->unique()->numberBetween(1, 20);

        return [
            'code' => 'GRADE-'.$level,
            'name' => 'Grade '.$level,
            'sort_order' => $level,
        ];
    }
}
