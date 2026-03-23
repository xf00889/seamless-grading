<?php

namespace App\Services\TeacherGradeEntry;

use App\Models\GradeSubmission;
use App\Models\QuarterlyGrade;
use App\Models\User;

class QuarterlyGradeSynchronizer
{
    public function sync(
        GradeSubmission $gradeSubmission,
        array $gradeRows,
        ?User $actor = null,
        ?string $reason = null,
    ): void {
        $existingQuarterlyGrades = $gradeSubmission->quarterlyGrades()
            ->get()
            ->keyBy('section_roster_id');

        foreach ($gradeRows as $gradeRow) {
            $quarterlyGrade = $existingQuarterlyGrades->get($gradeRow['section_roster_id'])
                ?? new QuarterlyGrade([
                    'grade_submission_id' => $gradeSubmission->id,
                    'section_roster_id' => $gradeRow['section_roster_id'],
                ]);

            $previousGrade = $quarterlyGrade->exists
                ? $this->comparableGradeValue($quarterlyGrade->grade)
                : null;
            $newGrade = $this->comparableGradeValue($gradeRow['grade']);

            $quarterlyGrade->fill([
                'grade' => $gradeRow['grade'],
                'remarks' => $gradeRow['remarks'],
            ]);

            if (! $quarterlyGrade->exists || $quarterlyGrade->isDirty()) {
                $quarterlyGrade->save();

                if ($quarterlyGrade->wasRecentlyCreated || $previousGrade === $newGrade) {
                    continue;
                }

                $quarterlyGrade->gradeChangeLogs()->create([
                    'changed_by' => $actor?->id,
                    'previous_grade' => $previousGrade,
                    'new_grade' => $newGrade,
                    'reason' => $reason,
                ]);
            }
        }
    }

    private function comparableGradeValue(mixed $grade): ?string
    {
        if ($grade === null || $grade === '') {
            return null;
        }

        return number_format((float) $grade, 2, '.', '');
    }
}
