<?php

namespace App\Actions\Adviser\Review;

use App\Enums\ApprovalAction;
use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Models\User;
use App\Services\AdviserReview\AdviserQuarterContextResolver;
use App\Services\AdviserReview\AdviserSubmissionWorkflowGuard;
use Illuminate\Support\Facades\DB;

class ReturnGradeSubmissionAction
{
    public function __construct(
        private readonly AdviserQuarterContextResolver $contextResolver,
        private readonly AdviserSubmissionWorkflowGuard $workflowGuard,
    ) {}

    public function handle(
        User $adviser,
        Section $section,
        GradingPeriod $gradingPeriod,
        GradeSubmission $gradeSubmission,
        string $remarks,
    ): GradeSubmission {
        return DB::transaction(function () use ($adviser, $section, $gradingPeriod, $gradeSubmission, $remarks): GradeSubmission {
            $lockedSubmission = GradeSubmission::query()
                ->with('teacherLoad')
                ->lockForUpdate()
                ->findOrFail($gradeSubmission->id);

            $this->contextResolver->assertSubmissionScope($section, $gradingPeriod, $lockedSubmission);
            $this->workflowGuard->ensureReturnable($lockedSubmission);

            $lockedSubmission->forceFill([
                'status' => GradeSubmissionStatus::Returned,
                'returned_at' => now(),
                'adviser_remarks' => $remarks,
            ])->save();

            $lockedSubmission->approvalLogs()->create([
                'acted_by' => $adviser->id,
                'action' => ApprovalAction::Returned,
                'remarks' => $remarks,
                'metadata' => [
                    'entity_type' => GradeSubmission::class,
                    'entity_id' => $lockedSubmission->id,
                    'section_id' => $section->id,
                    'grading_period_id' => $gradingPeriod->id,
                    'teacher_load_id' => $lockedSubmission->teacher_load_id,
                    'previous_status' => $gradeSubmission->status->value,
                ],
            ]);

            return $lockedSubmission->fresh([
                'teacherLoad.subject',
                'teacherLoad.teacher',
                'approvalLogs',
            ]);
        });
    }
}
