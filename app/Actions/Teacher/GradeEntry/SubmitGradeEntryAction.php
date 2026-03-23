<?php

namespace App\Actions\Teacher\GradeEntry;

use App\Enums\ApprovalAction;
use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\TeacherLoad;
use App\Models\User;
use App\Services\TeacherGradeEntry\GradeEntryPayloadValidator;
use App\Services\TeacherGradeEntry\QuarterlyGradeSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitGradeEntryAction
{
    public function __construct(
        private readonly GradeEntryPayloadValidator $gradeEntryPayloadValidator,
        private readonly QuarterlyGradeSynchronizer $quarterlyGradeSynchronizer,
    ) {}

    public function handle(
        User $teacher,
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        array $grades,
    ): GradeSubmission {
        return DB::transaction(function () use ($teacher, $teacherLoad, $gradingPeriod, $grades): GradeSubmission {
            $submission = GradeSubmission::query()
                ->where('teacher_load_id', $teacherLoad->id)
                ->where('grading_period_id', $gradingPeriod->id)
                ->lockForUpdate()
                ->first();

            $this->ensureSubmittable($teacherLoad, $submission);

            $payload = $this->gradeEntryPayloadValidator->validate(
                $teacherLoad,
                $gradingPeriod,
                $grades,
                true,
                $submission,
            );

            $submission ??= GradeSubmission::query()->create([
                'teacher_load_id' => $teacherLoad->id,
                'grading_period_id' => $gradingPeriod->id,
                'status' => GradeSubmissionStatus::Draft,
            ]);

            $previousStatus = $submission->status;

            $this->quarterlyGradeSynchronizer->sync(
                $submission,
                $payload['grade_rows'],
                $teacher,
                $previousStatus === GradeSubmissionStatus::Returned
                    ? 'Teacher corrected and resubmitted returned grades.'
                    : 'Teacher submitted grades.',
            );

            $submission->forceFill([
                'status' => GradeSubmissionStatus::Submitted,
                'submitted_by' => $teacher->id,
                'submitted_at' => now(),
            ])->save();

            $submission->approvalLogs()->create([
                'acted_by' => $teacher->id,
                'action' => ApprovalAction::Submitted,
                'remarks' => $previousStatus === GradeSubmissionStatus::Returned
                    ? 'Teacher resubmitted returned grades.'
                    : 'Teacher submitted grades.',
                'metadata' => [
                    'entity_type' => GradeSubmission::class,
                    'entity_id' => $submission->id,
                    'section_id' => $teacherLoad->section_id,
                    'teacher_load_id' => $teacherLoad->id,
                    'previous_status' => $previousStatus->value,
                    'grading_period_id' => $gradingPeriod->id,
                    'quarterly_grade_count' => count($payload['grade_rows']),
                ],
            ]);

            return $submission->fresh(['quarterlyGrades', 'approvalLogs']);
        });
    }

    private function ensureSubmittable(TeacherLoad $teacherLoad, ?GradeSubmission $submission): void
    {
        if (! $teacherLoad->is_active) {
            throw ValidationException::withMessages([
                'form.record' => 'This teaching load is inactive and cannot accept grade submissions.',
            ]);
        }

        if ($submission === null) {
            return;
        }

        if ($submission->locked_at !== null || $submission->status === GradeSubmissionStatus::Locked) {
            throw ValidationException::withMessages([
                'form.record' => 'This submission is locked and cannot be edited.',
            ]);
        }

        if ($submission->status === GradeSubmissionStatus::Submitted) {
            throw ValidationException::withMessages([
                'form.record' => 'This submission has already been submitted and must be returned before you can resubmit it.',
            ]);
        }

        if ($submission->status === GradeSubmissionStatus::Approved) {
            throw ValidationException::withMessages([
                'form.record' => 'This submission has already been approved and can no longer be changed.',
            ]);
        }
    }
}
