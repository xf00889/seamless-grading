<?php

namespace App\Actions\Admin\SubmissionMonitoring;

use App\Enums\ApprovalAction;
use App\Enums\GradeSubmissionStatus;
use App\Enums\TemplateDocumentType;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\User;
use App\Services\AdminMonitoring\SectionQuarterSummaryService;
use App\Services\AdviserReview\AdviserQuarterContextResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LockQuarterRecordsAction
{
    public function __construct(
        private readonly AdviserQuarterContextResolver $contextResolver,
        private readonly SectionQuarterSummaryService $sectionQuarterSummaryService,
    ) {}

    public function handle(User $admin, Section $section, GradingPeriod $gradingPeriod): array
    {
        return DB::transaction(function () use ($admin, $section, $gradingPeriod): array {
            $lockedSection = Section::query()
                ->with([
                    'schoolYear:id,name',
                    'gradeLevel:id,name',
                    'adviser:id,name',
                ])
                ->findOrFail($section->id);

            $this->contextResolver->assertSectionPeriodScope($lockedSection, $gradingPeriod);

            GradeSubmission::query()
                ->where('grading_period_id', $gradingPeriod->id)
                ->whereHas('teacherLoad', fn ($query) => $query
                    ->where('section_id', $lockedSection->id)
                    ->where('school_year_id', $lockedSection->school_year_id))
                ->lockForUpdate()
                ->get();

            ReportCardRecord::query()
                ->where('section_id', $lockedSection->id)
                ->where('grading_period_id', $gradingPeriod->id)
                ->where('document_type', TemplateDocumentType::Sf9)
                ->lockForUpdate()
                ->get();

            $summary = $this->sectionQuarterSummaryService->summaryForSection($lockedSection, $gradingPeriod);

            if ($summary['is_locked']) {
                return ['locked_count' => $summary['locked_submission_count']];
            }

            if (! $summary['is_ready_for_lock']) {
                throw ValidationException::withMessages([
                    'record' => $summary['lock_blockers'][0] ?? 'Quarter records cannot be locked yet.',
                ]);
            }

            $submissions = GradeSubmission::query()
                ->with('teacherLoad')
                ->where('grading_period_id', $gradingPeriod->id)
                ->where('status', GradeSubmissionStatus::Approved)
                ->whereHas('teacherLoad', fn ($query) => $query
                    ->where('section_id', $lockedSection->id)
                    ->where('school_year_id', $lockedSection->school_year_id)
                    ->where('is_active', true))
                ->lockForUpdate()
                ->get();

            foreach ($submissions as $submission) {
                $previousStatus = $submission->status;

                $submission->forceFill([
                    'status' => GradeSubmissionStatus::Locked,
                    'locked_at' => now(),
                ])->save();

                $submission->approvalLogs()->create([
                    'acted_by' => $admin->id,
                    'action' => ApprovalAction::Locked,
                    'remarks' => 'Administrator locked quarter records after adviser completion.',
                    'metadata' => [
                        'entity_type' => GradeSubmission::class,
                        'entity_id' => $submission->id,
                        'section_id' => $lockedSection->id,
                        'grading_period_id' => $gradingPeriod->id,
                        'teacher_load_id' => $submission->teacher_load_id,
                        'previous_status' => $previousStatus->value,
                        'operation_scope' => 'section_quarter_lock',
                    ],
                ]);
            }

            return ['locked_count' => $submissions->count()];
        });
    }
}
