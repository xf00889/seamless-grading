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

class ApproveGradeSubmissionAction
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
    ): GradeSubmission {
        return DB::transaction(function () use ($adviser, $section, $gradingPeriod, $gradeSubmission): GradeSubmission {
            $lockedSubmission = GradeSubmission::query()
                ->with('teacherLoad')
                ->lockForUpdate()
                ->findOrFail($gradeSubmission->id);

            $this->contextResolver->assertSubmissionScope($section, $gradingPeriod, $lockedSubmission);
            $this->workflowGuard->ensureApprovable($lockedSubmission);

            $lockedSubmission->forceFill([
                'status' => GradeSubmissionStatus::Approved,
                'approved_at' => now(),
            ])->save();

            $lockedSubmission->approvalLogs()->create([
                'acted_by' => $adviser->id,
                'action' => ApprovalAction::Approved,
                'remarks' => 'Adviser approved the submission.',
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
