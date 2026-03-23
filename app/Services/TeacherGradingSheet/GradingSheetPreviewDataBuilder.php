<?php

namespace App\Services\TeacherGradingSheet;

use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\QuarterlyGrade;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Services\TemplateManagement\TemplateMappingStatusEvaluator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GradingSheetPreviewDataBuilder
{
    public function __construct(
        private readonly ActiveGradingSheetTemplateResolver $templateResolver,
        private readonly TemplateMappingStatusEvaluator $mappingStatusEvaluator,
    ) {}

    public function build(TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): array
    {
        if ($teacherLoad->school_year_id !== $gradingPeriod->school_year_id) {
            throw new NotFoundHttpException;
        }

        $teacherLoad->loadMissing([
            'teacher',
            'schoolYear',
            'section.gradeLevel',
            'section.adviser',
            'subject',
        ]);

        $submission = GradeSubmission::query()
            ->with([
                'quarterlyGrades.sectionRoster.learner',
                'approvalLogs',
            ])
            ->where('teacher_load_id', $teacherLoad->id)
            ->where('grading_period_id', $gradingPeriod->id)
            ->first();

        $template = $this->templateResolver->resolve($teacherLoad);
        $templateMappingStatus = $template !== null
            ? $this->mappingStatusEvaluator->evaluate($template)
            : null;

        $templateFileMissing = $template !== null
            && ! Storage::disk($template->file_disk)->exists($template->file_path);

        $officialRosters = SectionRoster::query()
            ->with('learner')
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->select('section_rosters.*')
            ->where('section_rosters.section_id', $teacherLoad->section_id)
            ->where('section_rosters.school_year_id', $teacherLoad->school_year_id)
            ->where('section_rosters.is_official', true)
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->get();

        $quarterlyGrades = $submission?->quarterlyGrades->keyBy('section_roster_id') ?? collect();
        $consistencyIssues = $submission === null ? [] : $this->consistencyIssues($teacherLoad, $submission);
        $blockers = $this->blockers($submission, $template, $templateMappingStatus, $templateFileMissing, $consistencyIssues);

        return [
            'teacherLoad' => $teacherLoad,
            'gradingPeriod' => $gradingPeriod,
            'load' => [
                'teacher_name' => $teacherLoad->teacher->name,
                'subject_name' => $teacherLoad->subject->name,
                'subject_code' => $teacherLoad->subject->code,
                'section_name' => $teacherLoad->section->name,
                'grade_level_name' => $teacherLoad->section->gradeLevel->name,
                'school_year_name' => $teacherLoad->schoolYear->name,
                'adviser_name' => $teacherLoad->section->adviser?->name ?? 'No adviser assigned',
                'is_active' => $teacherLoad->is_active,
            ],
            'grading_period' => [
                'id' => $gradingPeriod->id,
                'quarter_label' => $gradingPeriod->quarter->label(),
                'starts_on' => $gradingPeriod->starts_on?->format('M d, Y'),
                'ends_on' => $gradingPeriod->ends_on?->format('M d, Y'),
                'is_open' => $gradingPeriod->is_open,
            ],
            'submission' => $this->submissionSummary($submission),
            'template' => $template === null ? null : [
                'model' => $template,
                'name' => $template->name,
                'scope' => $template->gradeLevel?->name ?? 'All grade levels',
                'version' => $template->version,
                'file_disk' => $template->file_disk,
                'file_path' => $template->file_path,
                'mapping_status' => $templateMappingStatus['status'],
                'mapping_summary' => $templateMappingStatus,
                'is_file_missing' => $templateFileMissing,
            ],
            'blockers' => $blockers,
            'preview_ready' => $blockers === [],
            'export_ready' => $blockers === [],
            'rows' => $blockers === []
                ? $officialRosters->map(fn (SectionRoster $sectionRoster): array => $this->rowPayload(
                    $sectionRoster,
                    $quarterlyGrades->get($sectionRoster->id),
                ))->values()->all()
                : [],
            'history' => GradingSheetExport::query()
                ->with(['template:id,name,version', 'exportedBy:id,name'])
                ->where('teacher_load_id', $teacherLoad->id)
                ->where('grading_period_id', $gradingPeriod->id)
                ->orderByDesc('version')
                ->get()
                ->map(fn (GradingSheetExport $gradingSheetExport): array => [
                    'model' => $gradingSheetExport,
                    'version' => $gradingSheetExport->version,
                    'template_version' => $gradingSheetExport->template_version,
                    'template_name' => $gradingSheetExport->template?->name ?? 'Unknown template',
                    'file_name' => $gradingSheetExport->file_name,
                    'exported_at' => $gradingSheetExport->exported_at?->format('M d, Y g:i A'),
                    'exported_by' => $gradingSheetExport->exportedBy?->name ?? 'Unknown user',
                ])->all(),
        ];
    }

    private function blockers(
        ?GradeSubmission $submission,
        mixed $template,
        ?array $templateMappingStatus,
        bool $templateFileMissing,
        array $consistencyIssues,
    ): array {
        $blockers = [];

        if ($template === null) {
            $blockers[] = 'No active grading-sheet template exists for this load. Activate a validated grading-sheet template before previewing or exporting.';
        } elseif ($templateMappingStatus !== null && $templateMappingStatus['status']['value'] !== 'complete') {
            $blockers[] = 'The active grading-sheet template has incomplete or broken field mappings. Activation or repair is required before previewing or exporting.';
        }

        if ($templateFileMissing) {
            $blockers[] = 'The active grading-sheet template file is missing from storage and cannot be used for preview or export.';
        }

        if ($submission === null) {
            $blockers[] = 'No saved grade submission exists yet for this teacher load and grading period.';
        }

        foreach ($consistencyIssues as $issue) {
            $blockers[] = $issue;
        }

        return $blockers;
    }

    private function consistencyIssues(TeacherLoad $teacherLoad, GradeSubmission $submission): array
    {
        $hasInconsistentRosterRows = QuarterlyGrade::query()
            ->where('grade_submission_id', $submission->id)
            ->whereDoesntHave('sectionRoster', fn ($query) => $query
                ->where('section_id', $teacherLoad->section_id)
                ->where('school_year_id', $teacherLoad->school_year_id)
                ->where('is_official', true))
            ->exists();

        return $hasInconsistentRosterRows
            ? ['Saved grade rows do not match the official roster for this teaching load and school year. Resolve the inconsistent persisted data before previewing or exporting.']
            : [];
    }

    private function rowPayload(SectionRoster $sectionRoster, ?QuarterlyGrade $quarterlyGrade): array
    {
        return [
            'section_roster_id' => $sectionRoster->id,
            'learner_name' => trim(sprintf(
                '%s, %s%s',
                $sectionRoster->learner->last_name,
                $sectionRoster->learner->first_name,
                $sectionRoster->learner->middle_name !== null
                    ? ' '.mb_substr($sectionRoster->learner->middle_name, 0, 1).'.'
                    : '',
            )),
            'lrn' => $sectionRoster->learner->lrn,
            'sex' => $sectionRoster->learner->sex->label(),
            'grade' => $quarterlyGrade?->grade !== null ? number_format((float) $quarterlyGrade->grade, 2, '.', '') : null,
            'remarks' => $quarterlyGrade?->remarks,
        ];
    }

    private function submissionSummary(?GradeSubmission $submission): array
    {
        $status = $submission?->status ?? GradeSubmissionStatus::Draft;

        return [
            'exists' => $submission !== null,
            'model' => $submission,
            'status' => [
                'value' => $status->value,
                'label' => $submission?->status?->label() ?? 'Not started',
                'tone' => $submission?->status?->tone() ?? 'slate',
            ],
            'adviser_remarks' => $submission?->adviser_remarks,
            'submitted_at' => $submission?->submitted_at?->format('M d, Y g:i A'),
            'returned_at' => $submission?->returned_at?->format('M d, Y g:i A'),
            'approved_at' => $submission?->approved_at?->format('M d, Y g:i A'),
            'locked_at' => $submission?->locked_at?->format('M d, Y g:i A'),
            'workflow_notice' => match ($submission?->status) {
                GradeSubmissionStatus::Draft => [
                    'tone' => 'amber',
                    'title' => 'Unofficial draft export',
                    'description' => 'This submission is still a draft. Any preview or export from this page is not an approved official record.',
                ],
                GradeSubmissionStatus::Submitted => [
                    'tone' => 'amber',
                    'title' => 'Pending adviser review',
                    'description' => 'This submission has been submitted but not yet approved. Any export from this page is still unofficial.',
                ],
                GradeSubmissionStatus::Returned => [
                    'tone' => 'amber',
                    'title' => 'Returned for correction',
                    'description' => 'This submission was returned with adviser remarks. Any export from this page remains unofficial until it is approved.',
                ],
                GradeSubmissionStatus::Locked => [
                    'tone' => 'rose',
                    'title' => 'Locked submission',
                    'description' => 'This submission is locked. Preview stays read-only, and the locked persisted data is what the export will use.',
                ],
                GradeSubmissionStatus::Approved => [
                    'tone' => 'emerald',
                    'title' => 'Approved submission',
                    'description' => 'This submission is approved and represents the official persisted grading data for this load and grading period.',
                ],
                default => [
                    'tone' => 'slate',
                    'title' => 'No saved submission',
                    'description' => 'Preview and export stay blocked until a grade submission has been saved for this load and grading period.',
                ],
            },
        ];
    }
}
