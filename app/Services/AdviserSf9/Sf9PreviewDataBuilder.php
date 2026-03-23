<?php

namespace App\Services\AdviserSf9;

use App\Enums\GradeSubmissionStatus;
use App\Enums\TemplateDocumentType;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\SystemSetting;
use App\Models\TeacherLoad;
use App\Services\AdviserReview\AdviserQuarterContextResolver;
use App\Services\AdviserReview\AdviserQuarterReviewService;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use App\Services\TemplateManagement\TemplateMappingStatusEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Sf9PreviewDataBuilder
{
    public function __construct(
        private readonly AdviserQuarterContextResolver $contextResolver,
        private readonly AdviserQuarterReviewService $reviewService,
        private readonly ActiveSf9TemplateResolver $templateResolver,
        private readonly TemplateMappingStatusEvaluator $mappingStatusEvaluator,
        private readonly LearnerMovementEligibilityService $eligibilityService,
    ) {}

    public function build(Section $section, GradingPeriod $gradingPeriod, SectionRoster $sectionRoster): array
    {
        $this->contextResolver->assertSectionRosterScope($section, $gradingPeriod, $sectionRoster);

        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);
        $sectionRoster->loadMissing('learner');
        $learnerPeriodEligibility = $this->eligibilityService->forGradingPeriod($sectionRoster, $gradingPeriod);

        $summary = $this->reviewService->sectionSummary($section, $gradingPeriod);
        $template = $this->templateResolver->resolve($section);
        $templateMappingStatus = $template !== null
            ? $this->mappingStatusEvaluator->evaluate($template)
            : null;
        $templateFileMissing = $template !== null
            && (blank($template->file_path) || ! Storage::disk($template->file_disk)->exists($template->file_path));

        [$subjectRequirements, $approvedSubjectRows] = $this->subjectData(
            $section,
            $gradingPeriod,
            $sectionRoster,
            $learnerPeriodEligibility,
        );
        $generalAverage = $this->generalAverage(
            $approvedSubjectRows,
            $summary['blockers'] === [] && count($subjectRequirements) === count($approvedSubjectRows),
        );
        $schoolName = (string) (SystemSetting::query()
            ->where('key', 'school.profile')
            ->first()?->value['name'] ?? config('app.name'));
        $sourceHash = $this->sourceHash(
            $section,
            $gradingPeriod,
            $sectionRoster,
            $schoolName,
            $approvedSubjectRows,
            $generalAverage,
        );
        $history = $this->history($sectionRoster, $gradingPeriod);
        $latestRecord = $history->first();

        $blockers = $this->exportBlockers(
            $summary['blockers'],
            $template,
            $templateMappingStatus,
            $templateFileMissing,
            $subjectRequirements,
            $approvedSubjectRows,
        );

        $finalizationBlockers = $blockers;

        if ($latestRecord === null) {
            $finalizationBlockers[] = 'Generate an SF9 export version before finalizing this learner record.';
        } elseif ((bool) $latestRecord['is_finalized']) {
            $finalizationBlockers[] = 'The latest SF9 export version is already finalized.';
        } elseif (
            $template !== null
            && (
                (int) $latestRecord['template_id'] !== $template->id
                || (int) $latestRecord['template_version'] !== $template->version
                || (string) ($latestRecord['source_hash'] ?? '') !== $sourceHash
            )
        ) {
            $finalizationBlockers[] = 'The latest SF9 export no longer matches the current approved data or active template. Generate a new export version before finalizing.';
        }

        return [
            'section' => $section,
            'gradingPeriod' => $gradingPeriod,
            'sectionRoster' => $sectionRoster,
            'summary' => $summary,
            'learner' => [
                'id' => $sectionRoster->learner->id,
                'name' => $this->learnerName($sectionRoster),
                'lrn' => $sectionRoster->learner->lrn,
                'enrollment_status' => [
                    'label' => $learnerPeriodEligibility['status']['label'],
                    'tone' => $learnerPeriodEligibility['status']['tone'],
                ],
                'eligibility_note' => $learnerPeriodEligibility['reason'],
                'effective_date' => $learnerPeriodEligibility['effective_date_label'],
            ],
            'template' => $template === null ? null : [
                'model' => $template,
                'id' => $template->id,
                'name' => $template->name,
                'scope' => $template->gradeLevel?->name ?? 'All grade levels',
                'version' => $template->version,
                'file_disk' => $template->file_disk,
                'file_path' => $template->file_path,
                'mapping_status' => $templateMappingStatus['status'],
                'mapping_summary' => $templateMappingStatus,
                'is_file_missing' => $templateFileMissing,
            ],
            'finalization_status' => $this->finalizationStatus($history),
            'blockers' => array_values(array_unique($blockers)),
            'finalization_blockers' => array_values(array_unique($finalizationBlockers)),
            'preview_ready' => $blockers === [],
            'export_ready' => $blockers === [],
            'finalize_ready' => $finalizationBlockers === [],
            'subject_requirements' => $subjectRequirements,
            'subject_rows' => $approvedSubjectRows,
            'general_average' => $generalAverage,
            'promotion_remarks' => null,
            'school_name' => $schoolName,
            'source_hash' => $sourceHash,
            'history' => $history->all(),
        ];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function subjectData(
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
        array $learnerPeriodEligibility,
    ): array {
        $teacherLoads = TeacherLoad::query()
            ->with([
                'subject:id,name,code',
                'teacher:id,name',
                'gradeSubmissions' => fn ($query) => $query
                    ->where('grading_period_id', $gradingPeriod->id)
                    ->with([
                        'quarterlyGrades' => fn ($gradeQuery) => $gradeQuery
                            ->where('section_roster_id', $sectionRoster->id),
                    ]),
            ])
            ->join('subjects', 'subjects.id', '=', 'teacher_loads.subject_id')
            ->select('teacher_loads.*')
            ->where('teacher_loads.section_id', $section->id)
            ->where('teacher_loads.school_year_id', $section->school_year_id)
            ->where('teacher_loads.is_active', true)
            ->orderBy('subjects.name')
            ->get();

        $subjectRequirements = [];
        $approvedSubjectRows = [];

        if (! $learnerPeriodEligibility['accepts_grade']) {
            foreach ($teacherLoads as $teacherLoad) {
                $subjectRequirements[] = [
                    'teacher_load_id' => $teacherLoad->id,
                    'subject_name' => $teacherLoad->subject->name,
                    'subject_code' => $teacherLoad->subject->code,
                    'teacher_name' => $teacherLoad->teacher->name,
                    'submission_status' => ['value' => 'exempt', 'label' => 'Not required', 'tone' => 'slate'],
                    'approved_at' => null,
                    'grade' => null,
                    'remarks' => null,
                    'blocker' => $learnerPeriodEligibility['reason'],
                ];
            }

            return [$subjectRequirements, []];
        }

        foreach ($teacherLoads as $teacherLoad) {
            /** @var GradeSubmission|null $submission */
            $submission = $teacherLoad->gradeSubmissions->first();
            $grade = $submission?->quarterlyGrades->first();
            $status = $submission?->status;
            $statusPayload = $status === null
                ? ['value' => 'missing', 'label' => 'Missing', 'tone' => 'rose']
                : ['value' => $status->value, 'label' => $status->label(), 'tone' => $status->tone()];
            $rowBlocker = null;

            if ($submission === null) {
                $rowBlocker = sprintf(
                    '%s is still missing for %s.',
                    $teacherLoad->subject->name,
                    $teacherLoad->teacher->name,
                );
            } elseif ($status !== GradeSubmissionStatus::Approved) {
                $rowBlocker = sprintf(
                    '%s is %s for %s.',
                    $teacherLoad->subject->name,
                    mb_strtolower($status->label()),
                    $teacherLoad->teacher->name,
                );
            } elseif ($grade === null) {
                $rowBlocker = sprintf(
                    'The approved %s submission does not contain a persisted grade for %s.',
                    $teacherLoad->subject->name,
                    $this->learnerName($sectionRoster),
                );
            }

            $subjectRequirements[] = [
                'teacher_load_id' => $teacherLoad->id,
                'subject_name' => $teacherLoad->subject->name,
                'subject_code' => $teacherLoad->subject->code,
                'teacher_name' => $teacherLoad->teacher->name,
                'submission_status' => $statusPayload,
                'approved_at' => $submission?->approved_at?->format('M d, Y g:i A'),
                'grade' => $rowBlocker === null && $grade?->grade !== null
                    ? number_format((float) $grade->grade, 2, '.', '')
                    : null,
                'remarks' => $rowBlocker === null ? $grade?->remarks : null,
                'blocker' => $rowBlocker,
            ];

            if ($rowBlocker === null) {
                $approvedSubjectRows[] = [
                    'teacher_load_id' => $teacherLoad->id,
                    'subject_name' => $teacherLoad->subject->name,
                    'subject_code' => $teacherLoad->subject->code,
                    'teacher_name' => $teacherLoad->teacher->name,
                    'grade' => number_format((float) $grade->grade, 2, '.', ''),
                    'remarks' => $grade->remarks,
                ];
            }
        }

        return [$subjectRequirements, $approvedSubjectRows];
    }

    /**
     * @param  array<int, string>  $summaryBlockers
     * @param  array<int, array<string, mixed>>  $subjectRequirements
     * @param  array<int, array<string, mixed>>  $approvedSubjectRows
     * @return array<int, string>
     */
    private function exportBlockers(
        array $summaryBlockers,
        mixed $template,
        ?array $templateMappingStatus,
        bool $templateFileMissing,
        array $subjectRequirements,
        array $approvedSubjectRows,
    ): array {
        $blockers = [];

        if ($template === null) {
            $blockers[] = 'No active SF9 template exists for this learner scope. Activate a validated SF9 template before previewing, exporting, or finalizing.';
        } elseif ($templateMappingStatus !== null && $templateMappingStatus['status']['value'] !== 'complete') {
            $blockers[] = 'The active SF9 template has incomplete or broken field mappings. Repair and reactivate the template before previewing, exporting, or finalizing.';
        }

        if ($templateFileMissing) {
            $blockers[] = 'The active SF9 template file is missing from storage and cannot be used for preview, export, or finalization.';
        }

        if ($subjectRequirements === []) {
            $blockers[] = 'No active subject assignments exist for this advisory section in the selected grading period.';
        }

        foreach ($summaryBlockers as $summaryBlocker) {
            $blockers[] = $summaryBlocker;
        }

        foreach ($subjectRequirements as $requirement) {
            if ($requirement['blocker'] !== null) {
                $blockers[] = $requirement['blocker'];
            }
        }

        if ($approvedSubjectRows === []) {
            $blockers[] = 'No approved consolidated data exists yet for this learner in the selected grading period.';
        }

        return $blockers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $approvedSubjectRows
     */
    private function generalAverage(array $approvedSubjectRows, bool $sectionReady): ?string
    {
        if (! $sectionReady || $approvedSubjectRows === []) {
            return null;
        }

        $average = collect($approvedSubjectRows)
            ->pluck('grade')
            ->filter(fn (mixed $grade): bool => is_numeric($grade))
            ->avg();

        return $average === null ? null : number_format((float) $average, 2, '.', '');
    }

    private function learnerName(SectionRoster $sectionRoster): string
    {
        return trim(sprintf(
            '%s, %s%s',
            $sectionRoster->learner->last_name,
            $sectionRoster->learner->first_name,
            $sectionRoster->learner->middle_name !== null
                ? ' '.mb_substr($sectionRoster->learner->middle_name, 0, 1).'.'
                : '',
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $approvedSubjectRows
     */
    private function sourceHash(
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
        string $schoolName,
        array $approvedSubjectRows,
        ?string $generalAverage,
    ): string {
        return hash('sha256', json_encode([
            'section_id' => $section->id,
            'section_roster_id' => $sectionRoster->id,
            'school_year_id' => $section->school_year_id,
            'grading_period_id' => $gradingPeriod->id,
            'school_name' => $schoolName,
            'general_average' => $generalAverage,
            'subject_rows' => $approvedSubjectRows,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function history(SectionRoster $sectionRoster, GradingPeriod $gradingPeriod): Collection
    {
        return ReportCardRecord::query()
            ->with(['template:id,name,version', 'generatedBy:id,name', 'finalizedBy:id,name'])
            ->where('section_roster_id', $sectionRoster->id)
            ->where('grading_period_id', $gradingPeriod->id)
            ->where('document_type', TemplateDocumentType::Sf9)
            ->orderByDesc('record_version')
            ->get()
            ->map(fn (ReportCardRecord $reportCardRecord): array => [
                'model' => $reportCardRecord,
                'id' => $reportCardRecord->id,
                'record_version' => $reportCardRecord->record_version,
                'template_id' => $reportCardRecord->template_id,
                'template_name' => $reportCardRecord->template?->name ?? 'Unknown template',
                'template_version' => $reportCardRecord->template_version,
                'file_name' => $reportCardRecord->file_name,
                'generated_at' => $reportCardRecord->generated_at?->format('M d, Y g:i A'),
                'generated_by' => $reportCardRecord->generatedBy?->name ?? 'Unknown user',
                'is_finalized' => $reportCardRecord->is_finalized,
                'finalized_at' => $reportCardRecord->finalized_at?->format('M d, Y g:i A'),
                'finalized_by' => $reportCardRecord->finalizedBy?->name,
                'source_hash' => data_get($reportCardRecord->payload, 'source_hash'),
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $history
     */
    private function finalizationStatus(Collection $history): array
    {
        if ($history->isEmpty()) {
            return [
                'label' => 'Not exported',
                'tone' => 'slate',
                'description' => 'No SF9 export version has been generated yet for this learner.',
            ];
        }

        $latest = $history->first();
        $latestFinalized = $history->firstWhere('is_finalized', true);

        if ((bool) $latest['is_finalized']) {
            return [
                'label' => 'Finalized',
                'tone' => 'emerald',
                'description' => 'Version '.$latest['record_version'].' is finalized and currently marked as the official adviser-approved SF9 record.',
            ];
        }

        if ($latestFinalized !== null) {
            return [
                'label' => 'Pending re-finalization',
                'tone' => 'amber',
                'description' => 'Version '.$latest['record_version'].' is newer than finalized version '.$latestFinalized['record_version'].'. Review and finalize the latest export before treating it as official.',
            ];
        }

        return [
            'label' => 'Not finalized',
            'tone' => 'amber',
            'description' => 'The latest export version still needs explicit adviser finalization.',
        ];
    }
}
