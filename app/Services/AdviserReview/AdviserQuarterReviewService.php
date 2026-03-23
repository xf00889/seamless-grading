<?php

namespace App\Services\AdviserReview;

use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Models\User;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use App\Support\Dashboard\BarChartPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdviserQuarterReviewService
{
    public function __construct(
        private readonly AdviserQuarterContextResolver $contextResolver,
        private readonly LearnerMovementEligibilityService $eligibilityService,
        private readonly BarChartPresenter $barChartPresenter,
    ) {}

    public function dashboard(User $adviser, array $filters): array
    {
        $context = $this->contextResolver->resolve($adviser, $filters);
        $search = trim((string) ($filters['search'] ?? ''));

        $sections = $this->sectionQuery($adviser, $context['selectedSchoolYearId'], $search)
            ->orderBy('name')
            ->get();

        $sectionSummaries = $context['selectedGradingPeriod'] !== null
            ? $this->summariesForSections($sections, $context['selectedGradingPeriod'])
            : collect();

        $expectedSubmissionCount = (int) $sectionSummaries->sum('expected_submission_count');
        $approvedSubmissionCount = (int) $sectionSummaries->sum('approved_submission_count');

        $totals = [
            'sections_in_scope' => $sectionSummaries->count(),
            'missing_submissions' => (int) $sectionSummaries->sum('missing_submission_count'),
            'returned_submissions' => (int) $sectionSummaries->sum('returned_submission_count'),
            'approved_submissions' => $approvedSubmissionCount,
            'ready_sections' => (int) $sectionSummaries
                ->filter(fn (array $summary): bool => $summary['is_ready_for_finalization'])
                ->count(),
            'completion_percentage' => $expectedSubmissionCount > 0
                ? (int) round(($approvedSubmissionCount / $expectedSubmissionCount) * 100)
                : 0,
        ];

        $attentionItems = $context['selectedGradingPeriod'] !== null
            ? $sectionSummaries
                ->filter(fn (array $summary): bool => $summary['missing_submission_count'] > 0 || $summary['returned_submission_count'] > 0 || ! $summary['is_ready_for_finalization'])
                ->take(5)
                ->map(fn (array $summary): array => [
                    'title' => $summary['section_name'],
                    'meta' => $summary['grade_level_name'].' · '.$summary['school_year_name'],
                    'description' => $summary['blockers'][0]
                        ?? 'This section still needs a complete approved submission set before consolidation.',
                    'route' => route('adviser.sections.tracker', [
                        'section' => $summary['section_id'],
                        'grading_period' => $context['selectedGradingPeriod'],
                    ]),
                    'badge' => $summary['status']['label'],
                    'badge_tone' => $summary['status']['tone'],
                ])
                ->values()
                ->all()
            : [];

        return [
            ...$context,
            'filters' => [
                'search' => $search,
                'school_year_id' => $context['selectedSchoolYearId'],
                'grading_period_id' => $context['selectedGradingPeriodId'],
            ],
            'sections' => $sectionSummaries->values()->all(),
            'totals' => $totals,
            'metrics' => [
                [
                    'eyebrow' => 'Section scope',
                    'label' => 'Sections in scope',
                    'value' => number_format($totals['sections_in_scope']),
                    'description' => $context['selectedGradingPeriod'] !== null
                        ? 'Advisory sections visible in '.$context['selectedGradingPeriod']->quarter->label().'.'
                        : 'Choose a grading period to review adviser-owned sections.',
                    'icon' => 'section',
                    'tone' => 'indigo',
                ],
                [
                    'eyebrow' => 'Missing work',
                    'label' => 'Missing submissions',
                    'value' => number_format($totals['missing_submissions']),
                    'description' => 'Teacher loads with no submission recorded yet for the selected quarter.',
                    'icon' => 'clock',
                    'tone' => $totals['missing_submissions'] > 0 ? 'rose' : 'emerald',
                ],
                [
                    'eyebrow' => 'Correction queue',
                    'label' => 'Returned submissions',
                    'value' => number_format($totals['returned_submissions']),
                    'description' => 'Returned items still waiting for teachers to correct and resubmit.',
                    'icon' => 'undo',
                    'tone' => $totals['returned_submissions'] > 0 ? 'amber' : 'emerald',
                ],
                [
                    'eyebrow' => 'Readiness signal',
                    'label' => 'Completion',
                    'value' => $totals['completion_percentage'].'%',
                    'description' => number_format($totals['ready_sections']).' section(s) are fully ready for finalization.',
                    'icon' => 'check-circle',
                    'tone' => $totals['completion_percentage'] >= 100 && $totals['sections_in_scope'] > 0 ? 'emerald' : 'slate',
                    'action_label' => 'Open sections',
                    'action_href' => route('adviser.sections.index', [
                        'school_year_id' => $context['selectedSchoolYearId'],
                        'grading_period_id' => $context['selectedGradingPeriodId'],
                    ]),
                ],
            ],
            'chart' => [
                'eyebrow' => 'Submission velocity',
                'title' => 'Quarter Readiness',
                'description' => 'Use this snapshot to spot missing, returned, and approved work before learner consolidation.',
                'items' => $this->barChartPresenter->present([
                    ['label' => 'Sections', 'value' => $totals['sections_in_scope']],
                    ['label' => 'Missing', 'value' => $totals['missing_submissions']],
                    ['label' => 'Returned', 'value' => $totals['returned_submissions']],
                    ['label' => 'Approved', 'value' => $totals['approved_submissions'], 'emphasis' => true],
                    ['label' => 'Ready', 'value' => $totals['ready_sections']],
                ]),
            ],
            'focus' => $context['selectedGradingPeriod'] !== null
                ? [
                    'eyebrow' => 'Academic advisory',
                    'title' => $totals['ready_sections'] > 0
                        ? 'Ready sections are available for consolidation review.'
                        : 'Quarter review still needs more approved submissions.',
                    'description' => $totals['ready_sections'] > 0
                        ? 'Only approved data should move into learner consolidation and official report preparation.'
                        : 'Returned, submitted, or missing subject loads must be cleared before the section becomes finalization-ready.',
                    'meta' => collect([
                        $context['selectedGradingPeriod']->quarter->label(),
                        $context['selectedGradingPeriod']->schoolYear?->name,
                    ])->filter()->implode(' · '),
                    'action_label' => 'Open advisory sections',
                    'action_href' => route('adviser.sections.index', [
                        'school_year_id' => $context['selectedSchoolYearId'],
                        'grading_period_id' => $context['selectedGradingPeriodId'],
                    ]),
                ]
                : [
                    'eyebrow' => 'Academic advisory',
                    'title' => 'Choose a grading period to unlock the adviser dashboard.',
                    'description' => 'Once a grading period is selected, this dashboard will surface section readiness, blockers, and consolidation context.',
                    'meta' => 'Selection required',
                    'action_label' => 'Open advisory sections',
                    'action_href' => route('adviser.sections.index'),
                ],
            'attentionItems' => $attentionItems,
        ];
    }

    public function sections(User $adviser, array $filters): array
    {
        $context = $this->contextResolver->resolve($adviser, $filters);
        $search = trim((string) ($filters['search'] ?? ''));

        $sections = $this->sectionQuery($adviser, $context['selectedSchoolYearId'], $search)
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $summaries = $context['selectedGradingPeriod'] !== null
            ? $this->summariesForSections(collect($sections->items()), $context['selectedGradingPeriod'])->keyBy('section_id')
            : collect();

        $sections->through(fn (Section $section): array => $summaries->get($section->id, $this->emptySectionSummary($section)));

        return [
            ...$context,
            'filters' => [
                'search' => $search,
                'school_year_id' => $context['selectedSchoolYearId'],
                'grading_period_id' => $context['selectedGradingPeriodId'],
            ],
            'sections' => $sections,
        ];
    }

    public function tracker(Section $section, GradingPeriod $gradingPeriod, array $filters): array
    {
        $this->contextResolver->assertSectionPeriodScope($section, $gradingPeriod);

        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $teacherLoads = TeacherLoad::query()
            ->with([
                'teacher:id,name',
                'subject:id,name,code',
                'gradeSubmissions' => fn ($query) => $query
                    ->where('grading_period_id', $gradingPeriod->id),
            ])
            ->join('subjects', 'subjects.id', '=', 'teacher_loads.subject_id')
            ->select('teacher_loads.*')
            ->where('teacher_loads.section_id', $section->id)
            ->where('teacher_loads.school_year_id', $section->school_year_id)
            ->where('teacher_loads.is_active', true)
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $teacherLoadQuery) use ($search): void {
                        $teacherLoadQuery
                            ->whereHas('subject', fn (Builder $subjectQuery) => $subjectQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%'))
                            ->orWhereHas('teacher', fn (Builder $teacherQuery) => $teacherQuery
                                ->where('name', 'like', '%'.$search.'%'));
                    });
                },
            )
            ->when(
                $status === 'missing',
                fn (Builder $query) => $query->whereDoesntHave(
                    'gradeSubmissions',
                    fn (Builder $submissionQuery) => $submissionQuery->where('grading_period_id', $gradingPeriod->id),
                ),
            )
            ->when(
                $status !== '' && $status !== 'missing',
                fn (Builder $query) => $query->whereHas(
                    'gradeSubmissions',
                    fn (Builder $submissionQuery) => $submissionQuery
                        ->where('grading_period_id', $gradingPeriod->id)
                        ->where('status', $status),
                ),
            )
            ->orderBy('subjects.name')
            ->paginate(10)
            ->withQueryString();

        $teacherLoads->through(fn (TeacherLoad $teacherLoad): array => $this->presentTrackerRow($teacherLoad));

        return [
            'section' => $section,
            'gradingPeriod' => $gradingPeriod,
            'filters' => [
                'search' => $search,
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
            'summary' => $this->sectionSummary($section, $gradingPeriod),
            'teacherLoads' => $teacherLoads,
        ];
    }

    public function review(Section $section, GradingPeriod $gradingPeriod, GradeSubmission $gradeSubmission): array
    {
        $gradeSubmission->loadMissing([
            'gradingPeriod',
            'teacherLoad.schoolYear',
            'teacherLoad.section.gradeLevel',
            'teacherLoad.section.adviser',
            'teacherLoad.subject',
            'teacherLoad.teacher',
            'quarterlyGrades.sectionRoster.learner',
            'approvalLogs' => fn ($query) => $query
                ->with('actedBy:id,name')
                ->latest(),
        ]);

        $this->contextResolver->assertSubmissionScope($section, $gradingPeriod, $gradeSubmission);

        $officialRosters = SectionRoster::query()
            ->with('learner')
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->select('section_rosters.*')
            ->where('section_rosters.section_id', $section->id)
            ->where('section_rosters.school_year_id', $section->school_year_id)
            ->where('section_rosters.is_official', true)
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->get();

        $gradesByRoster = $gradeSubmission->quarterlyGrades->keyBy('section_roster_id');

        return [
            'section' => $section->loadMissing([
                'schoolYear:id,name',
                'gradeLevel:id,name',
                'adviser:id,name',
            ]),
            'gradingPeriod' => $gradingPeriod,
            'summary' => $this->sectionSummary($section, $gradingPeriod),
            'submission' => [
                'id' => $gradeSubmission->id,
                'status' => $this->presentStatus($gradeSubmission->status),
                'subject_name' => $gradeSubmission->teacherLoad->subject->name,
                'subject_code' => $gradeSubmission->teacherLoad->subject->code,
                'teacher_name' => $gradeSubmission->teacherLoad->teacher->name,
                'submitted_at' => $gradeSubmission->submitted_at?->format('M d, Y g:i A'),
                'returned_at' => $gradeSubmission->returned_at?->format('M d, Y g:i A'),
                'approved_at' => $gradeSubmission->approved_at?->format('M d, Y g:i A'),
                'updated_at' => $gradeSubmission->updated_at?->format('M d, Y g:i A'),
                'adviser_remarks' => $gradeSubmission->adviser_remarks,
                'review_block_message' => $this->reviewBlockMessage($gradeSubmission),
                'is_decision_allowed' => $gradeSubmission->status === GradeSubmissionStatus::Submitted
                    && $gradeSubmission->locked_at === null,
            ],
            'rows' => $officialRosters
                ->map(function (SectionRoster $sectionRoster) use ($gradesByRoster, $gradingPeriod): array {
                    $grade = $gradesByRoster->get($sectionRoster->id);
                    $eligibility = $this->eligibilityService->forGradingPeriod($sectionRoster, $gradingPeriod);

                    return [
                        'learner_name' => sprintf(
                            '%s, %s%s',
                            $sectionRoster->learner->last_name,
                            $sectionRoster->learner->first_name,
                            $sectionRoster->learner->middle_name !== null
                                ? ' '.mb_substr($sectionRoster->learner->middle_name, 0, 1).'.'
                                : '',
                        ),
                        'lrn' => $sectionRoster->learner->lrn,
                        'enrollment_status' => [
                            'label' => $eligibility['status']['label'],
                            'tone' => $eligibility['status']['tone'],
                        ],
                        'grade' => $grade?->grade !== null ? number_format((float) $grade->grade, 2, '.', '') : null,
                        'remarks' => $grade?->remarks,
                        'accepts_grade' => $eligibility['accepts_grade'],
                        'eligibility_note' => $eligibility['reason'],
                    ];
                })
                ->values()
                ->all(),
            'approvalLogs' => $gradeSubmission->approvalLogs
                ->map(fn ($approvalLog): array => [
                    'action' => $approvalLog->action->value,
                    'label' => str($approvalLog->action->value)->replace('_', ' ')->title()->value(),
                    'acted_by' => $approvalLog->actedBy?->name ?? 'Unknown user',
                    'remarks' => $approvalLog->remarks,
                    'created_at' => $approvalLog->created_at?->format('M d, Y g:i A'),
                ])
                ->all(),
        ];
    }

    public function sectionSummary(Section $section, GradingPeriod $gradingPeriod): array
    {
        $this->contextResolver->assertSectionPeriodScope($section, $gradingPeriod);

        return $this->summariesForSections(collect([$section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
        ])]), $gradingPeriod)->first() ?? $this->emptySectionSummary($section);
    }

    /**
     * @param  Collection<int, Section>  $sections
     * @return Collection<int, array<string, mixed>>
     */
    public function summariesForSections(Collection $sections, GradingPeriod $gradingPeriod): Collection
    {
        if ($sections->isEmpty()) {
            return collect();
        }

        $teacherLoadsBySection = TeacherLoad::query()
            ->with([
                'teacher:id,name',
                'subject:id,name,code',
                'gradeSubmissions' => fn ($query) => $query
                    ->where('grading_period_id', $gradingPeriod->id),
            ])
            ->whereIn('section_id', $sections->pluck('id'))
            ->where('school_year_id', $gradingPeriod->school_year_id)
            ->where('is_active', true)
            ->get()
            ->groupBy('section_id');

        return $sections
            ->map(fn (Section $section): array => $this->buildSectionSummary(
                $section,
                $teacherLoadsBySection->get($section->id, collect()),
            ))
            ->values();
    }

    private function sectionQuery(User $adviser, ?int $selectedSchoolYearId, string $search): Builder
    {
        return Section::query()
            ->with([
                'schoolYear:id,name',
                'gradeLevel:id,name',
            ])
            ->where('adviser_id', $adviser->id)
            ->when($selectedSchoolYearId !== null, fn (Builder $query) => $query->where('school_year_id', $selectedSchoolYearId))
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $sectionQuery) use ($search): void {
                        $sectionQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhereHas('gradeLevel', fn (Builder $gradeLevelQuery) => $gradeLevelQuery
                                ->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('schoolYear', fn (Builder $schoolYearQuery) => $schoolYearQuery
                                ->where('name', 'like', '%'.$search.'%'));
                    });
                },
            );
    }

    /**
     * @param  Collection<int, TeacherLoad>  $teacherLoads
     */
    private function buildSectionSummary(Section $section, Collection $teacherLoads): array
    {
        $counts = [
            'missing' => 0,
            GradeSubmissionStatus::Draft->value => 0,
            GradeSubmissionStatus::Submitted->value => 0,
            GradeSubmissionStatus::Returned->value => 0,
            GradeSubmissionStatus::Approved->value => 0,
            GradeSubmissionStatus::Locked->value => 0,
        ];

        $missingSubjects = [];
        $blockers = [];

        $teacherLoads
            ->sortBy(fn (TeacherLoad $teacherLoad): string => mb_strtolower($teacherLoad->subject->name.' '.$teacherLoad->teacher->name))
            ->each(function (TeacherLoad $teacherLoad) use (&$counts, &$missingSubjects, &$blockers): void {
                $gradeSubmission = $teacherLoad->gradeSubmissions->first();

                if ($gradeSubmission === null) {
                    $counts['missing']++;
                    $missingSubjects[] = sprintf('%s (%s)', $teacherLoad->subject->name, $teacherLoad->teacher->name);
                    $blockers[] = sprintf(
                        '%s is still missing for %s.',
                        $teacherLoad->subject->name,
                        $teacherLoad->teacher->name,
                    );

                    return;
                }

                $counts[$gradeSubmission->status->value]++;

                if ($gradeSubmission->status !== GradeSubmissionStatus::Approved) {
                    $blockers[] = sprintf(
                        '%s is %s for %s.',
                        $teacherLoad->subject->name,
                        mb_strtolower($gradeSubmission->status->label()),
                        $teacherLoad->teacher->name,
                    );
                }
            });

        $expectedSubmissionCount = $teacherLoads->count();
        $approvedSubmissionCount = $counts[GradeSubmissionStatus::Approved->value];
        $isReadyForFinalization = $expectedSubmissionCount > 0
            && $approvedSubmissionCount === $expectedSubmissionCount;

        return [
            'section_id' => $section->id,
            'section_name' => $section->name,
            'grade_level_name' => $section->gradeLevel->name,
            'school_year_name' => $section->schoolYear->name,
            'expected_submission_count' => $expectedSubmissionCount,
            'approved_submission_count' => $approvedSubmissionCount,
            'returned_submission_count' => $counts[GradeSubmissionStatus::Returned->value],
            'missing_submission_count' => $counts['missing'],
            'completion_percentage' => $expectedSubmissionCount > 0
                ? (int) round(($approvedSubmissionCount / $expectedSubmissionCount) * 100)
                : 0,
            'is_ready_for_finalization' => $isReadyForFinalization,
            'status' => [
                'label' => $isReadyForFinalization ? 'Ready for finalization' : 'Not ready',
                'tone' => $isReadyForFinalization ? 'emerald' : 'amber',
            ],
            'counts' => $counts,
            'missing_subjects' => array_values($missingSubjects),
            'blockers' => array_values($blockers),
        ];
    }

    private function emptySectionSummary(Section $section): array
    {
        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
        ]);

        return [
            'section_id' => $section->id,
            'section_name' => $section->name,
            'grade_level_name' => $section->gradeLevel->name,
            'school_year_name' => $section->schoolYear->name,
            'expected_submission_count' => 0,
            'approved_submission_count' => 0,
            'returned_submission_count' => 0,
            'missing_submission_count' => 0,
            'completion_percentage' => 0,
            'is_ready_for_finalization' => false,
            'status' => [
                'label' => 'No grading period selected',
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
            'blockers' => [],
        ];
    }

    private function presentTrackerRow(TeacherLoad $teacherLoad): array
    {
        $gradeSubmission = $teacherLoad->gradeSubmissions->first();
        $status = $gradeSubmission !== null
            ? $this->presentStatus($gradeSubmission->status)
            : ['value' => 'missing', 'label' => 'Missing', 'tone' => 'rose'];

        return [
            'teacher_load_id' => $teacherLoad->id,
            'teacher_name' => $teacherLoad->teacher->name,
            'subject_name' => $teacherLoad->subject->name,
            'subject_code' => $teacherLoad->subject->code,
            'status' => $status,
            'submission_id' => $gradeSubmission?->id,
            'submitted_at' => $gradeSubmission?->submitted_at?->format('M d, Y g:i A'),
            'returned_at' => $gradeSubmission?->returned_at?->format('M d, Y g:i A'),
            'approved_at' => $gradeSubmission?->approved_at?->format('M d, Y g:i A'),
            'adviser_remarks' => $gradeSubmission?->adviser_remarks,
        ];
    }

    private function presentStatus(GradeSubmissionStatus $status): array
    {
        return [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ];
    }

    private function reviewBlockMessage(GradeSubmission $gradeSubmission): ?string
    {
        if ($gradeSubmission->locked_at !== null || $gradeSubmission->status === GradeSubmissionStatus::Locked) {
            return 'This submission is locked and cannot be reviewed until an administrator reopens it.';
        }

        return match ($gradeSubmission->status) {
            GradeSubmissionStatus::Draft => 'This submission is still in draft and must be submitted by the teacher before adviser review.',
            GradeSubmissionStatus::Returned => 'This submission has already been returned and is waiting for teacher correction and resubmission.',
            GradeSubmissionStatus::Approved => 'This submission has already been approved and is already counted in consolidation.',
            default => null,
        };
    }
}
