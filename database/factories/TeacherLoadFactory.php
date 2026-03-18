<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherLoad>
 */
class TeacherLoadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'school_year_id' => fn (array $attributes): int => Section::query()
                ->findOrFail($attributes['section_id'])
                ->school_year_id,
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'is_active' => true,
        ];
    }
}
