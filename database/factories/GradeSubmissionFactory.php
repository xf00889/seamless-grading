<?php

namespace Database\Factories;

use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\TeacherLoad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeSubmission>
 */
class GradeSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teacher_load_id' => TeacherLoad::factory(),
            'grading_period_id' => fn (array $attributes): int => $this->gradingPeriodIdForTeacherLoad(
                $attributes['teacher_load_id'],
            ),
            'status' => GradeSubmissionStatus::Draft,
            'submitted_by' => User::factory(),
            'adviser_remarks' => null,
            'submitted_at' => null,
            'returned_at' => null,
            'approved_at' => null,
            'locked_at' => null,
        ];
    }

    protected function gradingPeriodIdForTeacherLoad(int $teacherLoadId): int
    {
        $schoolYearId = TeacherLoad::query()->findOrFail($teacherLoadId)->school_year_id;

        return GradingPeriod::query()->firstOrCreate(
            [
                'school_year_id' => $schoolYearId,
                'quarter' => GradingQuarter::First,
            ],
            [
                'starts_on' => null,
                'ends_on' => null,
                'is_open' => false,
            ],
        )->id;
    }
}
