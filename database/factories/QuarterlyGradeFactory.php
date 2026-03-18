<?php

namespace Database\Factories;

use App\Models\GradeSubmission;
use App\Models\QuarterlyGrade;
use App\Models\SectionRoster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuarterlyGrade>
 */
class QuarterlyGradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grade_submission_id' => GradeSubmission::factory(),
            'section_roster_id' => fn (array $attributes): int => SectionRoster::factory()->create(
                $this->rosterAttributesForSubmission($attributes['grade_submission_id']),
            )->id,
            'grade' => fake()->randomFloat(2, 65, 99),
            'remarks' => fake()->randomElement(['Passed', 'Conditional', 'Incomplete']),
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function rosterAttributesForSubmission(int $gradeSubmissionId): array
    {
        $submission = GradeSubmission::query()
            ->with(['teacherLoad', 'gradingPeriod'])
            ->findOrFail($gradeSubmissionId);

        return [
            'school_year_id' => $submission->gradingPeriod->school_year_id,
            'section_id' => $submission->teacherLoad->section_id,
        ];
    }
}
