<?php

namespace Database\Factories;

use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'adviser_id' => User::factory(),
            'name' => fake()->unique()->bothify('Section-##?'),
            'is_active' => true,
        ];
    }
}
