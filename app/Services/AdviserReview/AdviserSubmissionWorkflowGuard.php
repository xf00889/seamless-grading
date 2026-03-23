<?php

namespace App\Services\AdviserReview;

use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Services\TeacherGradeEntry\GradeEntryPayloadValidator;
use Illuminate\Validation\ValidationException;

class AdviserSubmissionWorkflowGuard
{
    public function __construct(
        private readonly GradeEntryPayloadValidator $gradeEntryPayloadValidator,
    ) {}

    public function ensureApprovable(GradeSubmission $gradeSubmission): void
    {
        $this->ensureDecisionAllowed($gradeSubmission);
        $gradeSubmission->loadMissing([
            'teacherLoad',
            'gradingPeriod',
            'quarterlyGrades',
        ]);

        $this->gradeEntryPayloadValidator->validate(
            $gradeSubmission->teacherLoad,
            $gradeSubmission->gradingPeriod,
            $gradeSubmission->quarterlyGrades
                ->mapWithKeys(fn ($quarterlyGrade): array => [
                    $quarterlyGrade->section_roster_id => [
                        'grade' => $quarterlyGrade->grade,
                    ],
                ])
                ->all(),
            true,
            $gradeSubmission,
        );
    }

    public function ensureReturnable(GradeSubmission $gradeSubmission): void
    {
        $this->ensureDecisionAllowed($gradeSubmission);
    }

    private function ensureDecisionAllowed(GradeSubmission $gradeSubmission): void
    {
        if ($gradeSubmission->locked_at !== null || $gradeSubmission->status === GradeSubmissionStatus::Locked) {
            throw ValidationException::withMessages([
                'submission' => 'This submission is locked and cannot be reviewed until an administrator reopens it.',
            ]);
        }

        if ($gradeSubmission->status === GradeSubmissionStatus::Submitted) {
            return;
        }

        $message = match ($gradeSubmission->status) {
            GradeSubmissionStatus::Draft => 'This submission is still in draft and must be submitted by the teacher before adviser review.',
            GradeSubmissionStatus::Returned => 'This submission has already been returned and must be resubmitted by the teacher before it can be reviewed again.',
            GradeSubmissionStatus::Approved => 'This submission has already been approved and cannot be reviewed again from this screen.',
            GradeSubmissionStatus::Locked => 'This submission is locked and cannot be reviewed until an administrator reopens it.',
        };

        throw ValidationException::withMessages([
            'submission' => $message,
        ]);
    }
}
