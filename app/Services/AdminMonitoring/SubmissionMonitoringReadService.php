<?php

namespace App\Services\AdminMonitoring;

use App\Enums\ApprovalAction;
use App\Enums\GradeSubmissionStatus;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\TeacherLoad;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginationLengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class SubmissionMonitoringReadService
{
    public function __construct(
        private readonly SectionQuarterSummaryService $sectionQuarterSummaryService,
        private readonly SubmissionDeadlineEvaluator $deadlineEvaluator,
    ) {}

    public function build(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $this->selectedStatus($filters['status'] ?? null);
        $availableSchoolYears = SchoolYear::query()
            ->select(['id', 'name', 'starts_on', 'is_active'])
            ->orderByDesc('starts_on')
            ->get();

        $selectedSchoolYearId = $this->selectedSchoolYearId(
            $availableSchoolYears,
            isset($filters['school_year_id']) ? (int) $filters['school_year_id'] : null,
        );

        $availableGradingPeriods = $selectedSchoolYearId === null
            ? collect()
            : GradingPeriod::query()
                ->select(['id', 'school_year_id', 'quarter', 'starts_on', 'ends_on', 'is_open'])
                ->where('school_year_id', $selectedSchoolYearId)
                ->orderBy('quarter')
                ->get();

        $selectedGradingPeriodId = $this->selectedGradingPeriodId(
            $availableGradingPeriods,
            isset($filters['grading_period_id']) ? (int) $filters['grading_period_id'] : null,
        );
        $selectedGradingPeriod = $availableGradingPeriods->firstWhere('id', $selectedGradingPeriodId);

        $availableSections = Section::query()
            ->with(['gradeLevel:id,name', 'adviser:id,name'])
            ->when($selectedSchoolYearId !== null, fn ($query) => $query->where('school_year_id', $selectedSchoolYearId))
            ->orderBy('name')
            ->get(['id', 'school_year_id', 'grade_level_id', 'adviser_id', 'name']);
        $selectedSectionId = $this->selectedId($availableSections, $filters['section_id'] ?? null);

        $availableAdvisers = User::query()
            ->select('users.id', 'users.name')
            ->whereHas('advisorySections', function ($query) use ($selectedSchoolYearId, $selectedSectionId): void {
                if ($selectedSchoolYearId !== null) {
                    $query->where('school_year_id', $selectedSchoolYearId);
                }

                if ($selectedSectionId !== null) {
                    $query->where('id', $selectedSectionId);
                }
            })
            ->orderBy('users.name')
            ->get();
        $selectedAdviserId = $this->selectedId($availableAdvisers, $filters['adviser_id'] ?? null);

        $availableTeachers = User::query()
            ->select('users.id', 'users.name')
            ->whereHas('teacherLoads', function ($query) use ($selectedSchoolYearId, $selectedSectionId, $selectedAdviserId): void {
                if ($selectedSchoolYearId !== null) {
                    $query->where('school_year_id', $selectedSchoolYearId);
                }

                if ($selectedSectionId !== null) {
                    $query->where('section_id', $selectedSectionId);
                }

                if ($selectedAdviserId !== null) {
                    $query->whereHas('section', fn ($sectionQuery) => $sectionQuery->where('adviser_id', $selectedAdviserId));
                }
            })
            ->orderBy('users.name')
            ->get();
        $selectedTeacherId = $this->selectedId($availableTeachers, $filters['teacher_id'] ?? null);

        $sectionQuery = Section::query()
            ->with([
                'schoolYear:id,name',
                'gradeLevel:id,name',
                'adviser:id,name',
            ])
            ->when($selectedSchoolYearId !== null, fn ($query) => $query->where('school_year_id', $selectedSchoolYearId))
            ->when($selectedSectionId !== null, fn ($query) => $query->whereKey($selectedSectionId))
            ->when($selectedAdviserId !== null, fn ($query) => $query->where('adviser_id', $selectedAdviserId))
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($sectionQuery) use ($search): void {
                        $sectionQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhereHas('gradeLevel', fn ($gradeLevelQuery) => $gradeLevelQuery
                                ->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('adviser', fn ($adviserQuery) => $adviserQuery
                                ->where('name', 'like', '%'.$search.'%'));
                    });
                },
            );

        $allSections = (clone $sectionQuery)->get();
        $allSectionSummaries = $selectedGradingPeriod instanceof GradingPeriod
            ? $this->sectionQuarterSummaryService->summariesForSections($allSections, $selectedGradingPeriod)
            : collect();

        $sections = (clone $sectionQuery)
            ->orderBy('name')
            ->paginate(10, ['*'], 'sections_page')
            ->withQueryString();

        if ($selectedGradingPeriod instanceof GradingPeriod) {
            $pageSectionSummaries = $this->sectionQuarterSummaryService
                ->summariesForSections(collect($sections->items()), $selectedGradingPeriod)
                ->keyBy('section_id');

            $sections->through(fn (Section $section): array => $pageSectionSummaries->get($section->id));
        } else {
            $sections->through(fn (Section $section): array => $this->emptySectionSummary($section));
        }

        $submissionRows = $selectedGradingPeriod instanceof GradingPeriod
            ? $this->submissionRowsPaginator(
                $selectedGradingPeriod,
                $selectedSchoolYearId,
                $selectedSectionId,
                $selectedAdviserId,
                $selectedTeacherId,
                $status,
                $search,
            )
            : $this->emptyPaginator(15, 'submissions_page');

        $totals = [
            'missing_submissions' => (int) $allSectionSummaries->sum('missing_submission_count'),
            'draft_submissions' => (int) $allSectionSummaries->sum('draft_submission_count'),
            'submitted_submissions' => (int) $allSectionSummaries->sum('submitted_submission_count'),
            'returned_submissions' => (int) $allSectionSummaries->sum('returned_submission_count'),
            'approved_submissions' => (int) $allSectionSummaries->sum('approved_submission_count'),
            'locked_submissions' => (int) $allSectionSummaries->sum('locked_submission_count'),
            'late_submissions' => (int) $allSectionSummaries->sum('late_submission_count'),
            'finalized_sf9_records' => (int) $allSectionSummaries->sum('finalized_sf9_count'),
            'official_roster_records' => (int) $allSectionSummaries->sum('official_roster_count'),
            'required_sf9_roster_records' => (int) $allSectionSummaries->sum('required_sf9_roster_count'),
            'completed_sections' => (int) $allSectionSummaries
                ->filter(fn (array $summary): bool => $summary['is_completed'])
                ->count(),
        ];

        return [
            'availableSchoolYears' => $availableSchoolYears,
            'availableGradingPeriods' => $availableGradingPeriods,
            'availableSections' => $availableSections,
            'availableAdvisers' => $availableAdvisers,
            'availableTeachers' => $availableTeachers,
            'selectedGradingPeriod' => $selectedGradingPeriod,
            'filters' => [
                'search' => $search,
                'school_year_id' => $selectedSchoolYearId,
                'grading_period_id' => $selectedGradingPeriodId,
                'section_id' => $selectedSectionId,
                'adviser_id' => $selectedAdviserId,
                'teacher_id' => $selectedTeacherId,
                'status' => $status,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'All statuses'],
                ['value' => 'missing', 'label' => 'Missing'],
                ...collect(GradeSubmissionStatus::cases())
                    ->map(fn (GradeSubmissionStatus $submissionStatus): array => [
                        'value' => $submissionStatus->value,
                        'label' => $submissionStatus->label(),
                    ])
                    ->all(),
            ],
            'totals' => $totals,
            'summaryCards' => $this->summaryCards($totals),
            'sections' => $sections,
            'submissionRows' => $submissionRows,
        ];
    }

    /**
     * @param  array{
     *     missing_submissions:int,
     *     draft_submissions:int,
     *     submitted_submissions:int,
     *     returned_submissions:int,
     *     approved_submissions:int,
     *     locked_submissions:int,
     *     late_submissions:int,
     *     finalized_sf9_records:int,
     *     official_roster_records:int,
     *     required_sf9_roster_records:int,
     *     completed_sections:int
     * }  $totals
     * @return list<array{
     *     label:string,
     *     value:int,
     *     icon:string,
     *     tone:string,
     *     status:string,
     *     status_tone:string
     * }>
     */
    private function summaryCards(array $totals): array
    {
        return [
            [
                'label' => 'Missing submissions',
                'value' => $totals['missing_submissions'],
                'icon' => 'monitor',
                'tone' => 'rose',
                'status' => 'Open',
                'status_tone' => 'rose',
            ],
            [
                'label' => 'Draft / Submitted',
                'value' => $totals['draft_submissions'] + $totals['submitted_submissions'],
                'icon' => 'clock',
                'tone' => 'sky',
                'status' => 'In review',
                'status_tone' => 'sky',
            ],
            [
                'label' => 'Returned work',
                'value' => $totals['returned_submissions'],
                'icon' => 'undo',
                'tone' => 'amber',
                'status' => 'Correction queue',
                'status_tone' => 'amber',
            ],
            [
                'label' => 'Approved / Locked',
                'value' => $totals['approved_submissions'] + $totals['locked_submissions'],
                'icon' => 'lock',
                'tone' => 'teal',
                'status' => 'Official',
                'status_tone' => 'teal',
            ],
            [
                'label' => 'Late submissions',
                'value' => $totals['late_submissions'],
                'icon' => 'clock',
                'tone' => 'amber',
                'status' => 'Deadline watch',
                'status_tone' => 'amber',
            ],
            [
                'label' => 'Completed sections',
                'value' => $totals['completed_sections'],
                'icon' => 'dashboard',
                'tone' => 'emerald',
                'status' => sprintf(
                    '%d/%d SF9 final',
                    $totals['finalized_sf9_records'],
                    $totals['required_sf9_roster_records'],
                ),
                'status_tone' => 'emerald',
            ],
        ];
    }

    private function submissionRowsPaginator(
        GradingPeriod $gradingPeriod,
        ?int $selectedSchoolYearId,
        ?int $selectedSectionId,
        ?int $selectedAdviserId,
        ?int $selectedTeacherId,
        string $status,
        string $search,
    ): LengthAwarePaginator {
        $query = TeacherLoad::query()
            ->with([
                'section:id,name,school_year_id,grade_level_id,adviser_id',
                'section.gradeLevel:id,name',
                'section.adviser:id,name',
                'teacher:id,name',
                'subject:id,name,code',
                'gradeSubmissions' => fn ($submissionQuery) => $submissionQuery
                    ->where('grading_period_id', $gradingPeriod->id)
                    ->with([
                        'approvalLogs' => fn ($approvalLogQuery) => $approvalLogQuery
                            ->latest(),
                    ]),
            ])
            ->join('subjects', 'subjects.id', '=', 'teacher_loads.subject_id')
            ->select('teacher_loads.*')
            ->when($selectedSchoolYearId !== null, fn ($builder) => $builder->where('teacher_loads.school_year_id', $selectedSchoolYearId))
            ->where('teacher_loads.is_active', true)
            ->when($selectedSectionId !== null, fn ($builder) => $builder->where('teacher_loads.section_id', $selectedSectionId))
            ->when($selectedAdviserId !== null, fn ($builder) => $builder->whereHas(
                'section',
                fn ($sectionQuery) => $sectionQuery->where('adviser_id', $selectedAdviserId),
            ))
            ->when($selectedTeacherId !== null, fn ($builder) => $builder->where('teacher_id', $selectedTeacherId))
            ->when(
                $search !== '',
                function ($builder) use ($search): void {
                    $builder->where(function ($rowQuery) use ($search): void {
                        $rowQuery
                            ->whereHas('subject', fn ($subjectQuery) => $subjectQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%'))
                            ->orWhereHas('teacher', fn ($teacherQuery) => $teacherQuery
                                ->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('section', fn ($sectionQuery) => $sectionQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhereHas('adviser', fn ($adviserQuery) => $adviserQuery
                                    ->where('name', 'like', '%'.$search.'%')));
                    });
                },
            )
            ->when(
                $status === 'missing',
                fn ($builder) => $builder->whereDoesntHave(
                    'gradeSubmissions',
                    fn ($submissionQuery) => $submissionQuery->where('grading_period_id', $gradingPeriod->id),
                ),
            )
            ->when(
                $status !== '' && $status !== 'missing',
                fn ($builder) => $builder->whereHas(
                    'gradeSubmissions',
                    fn ($submissionQuery) => $submissionQuery
                        ->where('grading_period_id', $gradingPeriod->id)
                        ->where('status', $status),
                ),
            );

        $paginator = $query
            ->orderBy('subjects.name')
            ->paginate(15, ['*'], 'submissions_page')
            ->withQueryString();

        $paginator->through(fn (TeacherLoad $teacherLoad): array => $this->presentSubmissionRow($teacherLoad, $gradingPeriod));

        return $paginator;
    }

    private function presentSubmissionRow(TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): array
    {
        $submission = $teacherLoad->gradeSubmissions->first();
        $status = $submission === null
            ? ['value' => 'missing', 'label' => 'Missing', 'tone' => 'rose']
            : ['value' => $submission->status->value, 'label' => $submission->status->label(), 'tone' => $submission->status->tone()];

        $deadline = $gradingPeriod->ends_on?->copy()->endOfDay();
        $isLate = $this->deadlineEvaluator->isLate($submission?->submitted_at, $deadline);

        return [
            'teacher_load_id' => $teacherLoad->id,
            'section_name' => $teacherLoad->section->name,
            'grade_level_name' => $teacherLoad->section->gradeLevel->name,
            'adviser_name' => $teacherLoad->section->adviser?->name ?? 'Unassigned',
            'teacher_name' => $teacherLoad->teacher->name,
            'subject_name' => $teacherLoad->subject->name,
            'subject_code' => $teacherLoad->subject->code,
            'status' => $status,
            'is_late' => $isLate,
            'late_reason' => $this->deadlineEvaluator->lateReason($submission?->submitted_at, $deadline),
            'was_reopened' => $submission?->approvalLogs->first()?->action === ApprovalAction::Reopened,
            'submission_id' => $submission?->id,
            'submitted_at' => $submission?->submitted_at?->format('M d, Y g:i A'),
            'returned_at' => $submission?->returned_at?->format('M d, Y g:i A'),
            'approved_at' => $submission?->approved_at?->format('M d, Y g:i A'),
            'locked_at' => $submission?->locked_at?->format('M d, Y g:i A'),
            'adviser_remarks' => $submission?->adviser_remarks,
        ];
    }

    private function emptySectionSummary(Section $section): array
    {
        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);

        return [
            'model' => $section,
            'section_id' => $section->id,
            'section_name' => $section->name,
            'grade_level_name' => $section->gradeLevel->name,
            'school_year_name' => $section->schoolYear->name,
            'adviser_name' => $section->adviser?->name ?? 'Unassigned',
            'expected_submission_count' => 0,
            'approved_submission_count' => 0,
            'locked_submission_count' => 0,
            'returned_submission_count' => 0,
            'submitted_submission_count' => 0,
            'draft_submission_count' => 0,
            'missing_submission_count' => 0,
            'late_submission_count' => 0,
            'official_roster_count' => 0,
            'required_sf9_roster_count' => 0,
            'finalized_sf9_count' => 0,
            'completion_percentage' => 0,
            'is_ready_for_lock' => false,
            'is_locked' => false,
            'is_completed' => false,
            'can_lock' => false,
            'can_reopen' => false,
            'status' => [
                'label' => 'Idle',
                'tone' => 'slate',
            ],
            'counts' => [
                'missing' => 0,
                GradeSubmissionStatus::Draft->value => 0,
                GradeSubmissionStatus::Submitted->value => 0,
                GradeSubmissionStatus::Returned->value => 0,
                GradeSubmissionStatus::Approved->value => 0,
                GradeSubmissionStatus::Locked->value => 0,
            ],
            'missing_subjects' => [],
            'lock_blockers' => [],
        ];
    }

    /**
     * @param  Collection<int, SchoolYear>  $availableSchoolYears
     */
    private function selectedSchoolYearId(Collection $availableSchoolYears, ?int $requestedSchoolYearId): ?int
    {
        if ($requestedSchoolYearId !== null && $availableSchoolYears->contains('id', $requestedSchoolYearId)) {
            return $requestedSchoolYearId;
        }

        return $availableSchoolYears->firstWhere('is_active', true)?->id
            ?? $availableSchoolYears->first()?->id;
    }

    /**
     * @param  Collection<int, GradingPeriod>  $availableGradingPeriods
     */
    private function selectedGradingPeriodId(Collection $availableGradingPeriods, ?int $requestedGradingPeriodId): ?int
    {
        if ($requestedGradingPeriodId !== null && $availableGradingPeriods->contains('id', $requestedGradingPeriodId)) {
            return $requestedGradingPeriodId;
        }

        return $availableGradingPeriods->firstWhere('is_open', true)?->id
            ?? $availableGradingPeriods->first()?->id;
    }

    /**
     * @param  Collection<int, mixed>  $availableRecords
     */
    private function selectedId(Collection $availableRecords, mixed $requestedId): ?int
    {
        $requestedId = is_numeric($requestedId) ? (int) $requestedId : null;

        if ($requestedId !== null && $availableRecords->contains('id', $requestedId)) {
            return $requestedId;
        }

        return null;
    }

    private function selectedStatus(mixed $status): string
    {
        $status = trim((string) $status);

        if ($status === 'missing') {
            return $status;
        }

        return collect(GradeSubmissionStatus::cases())
            ->first(fn (GradeSubmissionStatus $submissionStatus): bool => $submissionStatus->value === $status)
            ?->value ?? '';
    }

    private function emptyPaginator(int $perPage, string $pageName): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage($pageName);

        return (new PaginationLengthAwarePaginator(
            [],
            0,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        ))->withQueryString();
    }
}
