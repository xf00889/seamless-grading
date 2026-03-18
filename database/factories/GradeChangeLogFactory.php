<?php

namespace Database\Factories;

use App\Models\GradeChangeLog;
use App\Models\QuarterlyGrade;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeChangeLog>
 */
class GradeChangeLogFactory extends Factory
{
    public function definition(): array
    {
        $previous = fake()->randomFloat(2, 65, 90);
        $new = min($previous + fake()->randomFloat(2, 0.5, 5), 99.99);

        return [
            'quarterly_grade_id' => QuarterlyGrade::factory(),
            'changed_by' => User::factory(),
            'previous_grade' => $previous,
            'new_grade' => $new,
            'reason' => fake()->sentence(),
        ];
    }
}
