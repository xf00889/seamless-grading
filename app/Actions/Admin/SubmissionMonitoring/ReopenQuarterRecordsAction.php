<?php

namespace App\Actions\Admin\SubmissionMonitoring;

use App\Enums\ApprovalAction;
use App\Enums\GradeSubmissionStatus;
use App\Enums\TemplateDocumentType;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use App\Services\AdviserReview\AdviserQuarterContextResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenQuarterRecordsAction
{
    public function __construct(
        private readonly AdviserQuarterContextResolver $contextResolver,
    ) {}

    public function handle(User $admin, Section $section, GradingPeriod $gradingPeriod, string $reason): array
    {
        return DB::transaction(function () use ($admin, $section, $gradingPeriod, $reason): array {
            $lockedSection = Section::query()->findOrFail($section->id);

            $this->contextResolver->assertSectionPeriodScope($lockedSection, $gradingPeriod);

            $lockedSubmissions = GradeSubmission::query()
                ->with('teacherLoad')
                ->where('grading_period_id', $gradingPeriod->id)
                ->whereHas('teacherLoad', fn ($query) => $query
                    ->where('section_id', $lockedSection->id)
                    ->where('school_year_id', $lockedSection->school_year_id))
                ->where(function ($query): void {
                    $query
                        ->where('status', GradeSubmissionStatus::Locked)
                        ->orWhereNotNull('locked_at');
                })
                ->lockForUpdate()
                ->get();

            if ($lockedSubmissions->isEmpty()) {
                throw ValidationException::withMessages([
                    'record' => 'No locked quarter records exist for the selected section and grading period.',
                ]);
            }

            $sf9ReportCardQuery = ReportCardRecord::query()
                ->where('section_id', $lockedSection->id)
                ->where('grading_period_id', $gradingPeriod->id)
                ->where('document_type', TemplateDocumentType::Sf9)
                ->lockForUpdate();

            $sf9ReportCardQuery->get();

            $invalidatedSf9RecordCount = (clone $sf9ReportCardQuery)
                ->where('is_finalized', true)
                ->count();

            $sf9ReportCardQuery->update([
                'is_finalized' => false,
                'finalized_at' => null,
                'finalized_by' => null,
            ]);

            $officialRosterIds = SectionRoster::query()
                ->where('section_id', $lockedSection->id)
                ->where('school_year_id', $lockedSection->school_year_id)
                ->where('is_official', true)
                ->pluck('id');

            $invalidatedSf10RecordCount = 0;

            if ($officialRosterIds->isNotEmpty()) {
                $sf10ReportCardQuery = ReportCardRecord::query()
                    ->where('section_id', $lockedSection->id)
                    ->where('school_year_id', $lockedSection->school_year_id)
                    ->whereIn('section_roster_id', $officialRosterIds)
                    ->where('document_type', TemplateDocumentType::Sf10)
                    ->lockForUpdate();

                $sf10ReportCardQuery->get();

                $invalidatedSf10RecordCount = (clone $sf10ReportCardQuery)
                    ->where('is_finalized', true)
                    ->count();

                $sf10ReportCardQuery->update([
                    'is_finalized' => false,
                    'finalized_at' => null,
                    'finalized_by' => null,
                ]);
            }

            $invalidatedReportCardCount = $invalidatedSf9RecordCount + $invalidatedSf10RecordCount;

            foreach ($lockedSubmissions as $submission) {
                $previousStatus = $submission->status;

                $submission->forceFill([
                    'status' => GradeSubmissionStatus::Returned,
                    'approved_at' => null,
                    'locked_at' => null,
                    'returned_at' => now(),
                    'adviser_remarks' => $this->mergedRemarks($submission->adviser_remarks, $reason),
                ])->save();

                $submission->approvalLogs()->create([
                    'acted_by' => $admin->id,
                    'action' => ApprovalAction::Reopened,
                    'remarks' => $reason,
                    'metadata' => [
                        'entity_type' => GradeSubmission::class,
                        'entity_id' => $submission->id,
                        'section_id' => $lockedSection->id,
                        'grading_period_id' => $gradingPeriod->id,
                        'teacher_load_id' => $submission->teacher_load_id,
                        'previous_status' => $previousStatus->value,
                        'operation_scope' => 'section_quarter_reopen',
                        'invalidated_sf9_record_count' => $invalidatedSf9RecordCount,
                        'invalidated_sf10_record_count' => $invalidatedSf10RecordCount,
                        'invalidated_report_card_count' => $invalidatedReportCardCount,
                    ],
                ]);
            }

            return [
                'reopened_count' => $lockedSubmissions->count(),
                'invalidated_report_card_count' => $invalidatedReportCardCount,
            ];
        });
    }

    private function mergedRemarks(?string $existingRemarks, string $reason): string
    {
        $reopenRemark = 'Admin reopen reason: '.trim($reason);

        if (blank($existingRemarks)) {
            return $reopenRemark;
        }

        if (str_contains($existingRemarks, $reopenRemark)) {
            return $existingRemarks;
        }

        return trim($existingRemarks."\n\n".$reopenRemark);
    }
}
