<?php

namespace App\Services\AdviserYearEnd;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\LearnerYearEndStatus;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use App\Services\TeacherGradeEntry\GradingRuleResolver;
use Illuminate\Support\Collection;

class YearEndGradeReadinessService
{
    public function __construct(
        private readonly AdviserYearEndContextResolver $contextResolver,
        private readonly GradingRuleResolver $gradingRuleResolver,
        private readonly LearnerMovementEligibilityService $eligibilityService,
    ) {}

    /**
     * @param  Collection<int, SectionRoster>  $sectionRosters
     * @return Collection<int, array<string, mixed>>
     */
    public function summariesForRosters(Section $section, Collection $sectionRosters): Collection
    {
        if ($sectionRosters->isEmpty()) {
            return collect();
        }

        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);
        $sectionRosters->each(fn (SectionRoster $sectionRoster) => $sectionRoster->loadMissing([
            'learner',
            'yearEndStatusSetBy:id,name',
        ]));

        $gradingPeriods = GradingPeriod::query()
            ->where('school_year_id', $section->school_year_id)
            ->orderBy('quarter')
            ->get();

        $finalGradingPeriod = $gradingPeriods
            ->sortByDesc(fn (GradingPeriod $gradingPeriod): int => $gradingPeriod->quarter->value)
            ->first();
        $teacherLoads = $this->teacherLoads($section, $gradingPeriods, $sectionRosters);
        $rules = $this->gradingRuleResolver->resolve();

        return $sectionRosters
            ->map(fn (SectionRoster $sectionRoster): array => $this->buildRosterSummary(
                $section,
                $sectionRoster,
                $gradingPeriods,
                $finalGradingPeriod,
                $teacherLoads,
                $rules,
            ))
            ->values();
    }

    public function detail(Section $section, SectionRoster $sectionRoster): array
    {
        $this->contextResolver->assertSectionRosterScope($section, $sectionRoster);

        return $this->summariesForRosters($section, collect([$sectionRoster]))->first()
            ?? $this->emptySummary($section, $sectionRoster);
    }

    /**
     * @param  Collection<int, GradingPeriod>  $gradingPeriods
     * @param  Collection<int, SectionRoster>  $sectionRosters
     * @return Collection<int, TeacherLoad>
     */
    private function teacherLoads(
        Section $section,
        Collection $gradingPeriods,
        Collection $sectionRosters,
    ): Collection {
        $gradingPeriodIds = $gradingPeriods->pluck('id')->all();
        $sectionRosterIds = $sectionRosters->pluck('id')->all();

        return TeacherLoad::query()
            ->with([
                'subject:id,name,code',
                'gradeSubmissions' => fn ($query) => $query
                    ->whereIn('grading_period_id', $gradingPeriodIds)
                    ->with([
                        'quarterlyGrades' => fn ($gradeQuery) => $gradeQuery
                            ->whereIn('section_roster_id', $sectionRosterIds),
                    ]),
            ])
            ->join('subjects', 'subjects.id', '=', 'teacher_loads.subject_id')
            ->select('teacher_loads.*')
            ->where('teacher_loads.section_id', $section->id)
            ->where('teacher_loads.school_year_id', $section->school_year_id)
            ->where('teacher_loads.is_active', true)
            ->orderBy('subjects.name')
            ->get();
    }

    /**
     * @param  Collection<int, GradingPeriod>  $gradingPeriods
     * @param  Collection<int, TeacherLoad>  $teacherLoads
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function buildRosterSummary(
        Section $section,
        SectionRoster $sectionRoster,
        Collection $gradingPeriods,
        ?GradingPeriod $finalGradingPeriod,
        Collection $teacherLoads,
        array $rules,
    ): array {
        $fullYearBlockers = [];
        $subjectRows = [];
        $approvedYearEndRows = [];
        $finalQuarterReadyCount = 0;
        $fullYearReadyCount = 0;
        $expectedFinalQuarterSubjectCount = 0;
        $expectedSubjectCount = 0;

        if ($gradingPeriods->isEmpty()) {
            $fullYearBlockers[] = 'No grading periods exist for this school year, so year-end readiness cannot be evaluated.';
        }

        if ($teacherLoads->isEmpty()) {
            $fullYearBlockers[] = 'No active subject assignments exist for this advisory section in the selected school year.';
        }

        foreach ($teacherLoads as $teacherLoad) {
            $submissionByPeriod = $teacherLoad->gradeSubmissions->keyBy('grading_period_id');
            $subjectGrades = [];
            $subjectBlockers = [];
            $finalQuarterReady = true;
            $hasApplicableQuarter = false;
            $finalQuarterApplicable = false;

            foreach ($gradingPeriods as $gradingPeriod) {
                $periodEligibility = $this->eligibilityService->forGradingPeriod($sectionRoster, $gradingPeriod);

                if (! $periodEligibility['accepts_grade']) {
                    continue;
                }

                $hasApplicableQuarter = true;
                $submission = $submissionByPeriod->get($gradingPeriod->id);
                $quarterlyGrade = $submission?->quarterlyGrades
                    ->firstWhere('section_roster_id', $sectionRoster->id);

                if ($submission === null) {
                    $subjectBlockers[] = sprintf(
                        '%s is missing an approved %s submission.',
                        $teacherLoad->subject->name,
                        $gradingPeriod->quarter->label(),
                    );

                    continue;
                }

                if ($submission->status !== GradeSubmissionStatus::Approved) {
                    $subjectBlockers[] = sprintf(
                        '%s is %s for %s.',
                        $teacherLoad->subject->name,
                        mb_strtolower($submission->status->label()),
                        $gradingPeriod->quarter->label(),
                    );

                    continue;
                }

                if ($quarterlyGrade === null || $quarterlyGrade->grade === null) {
                    $subjectBlockers[] = sprintf(
                        '%s has no persisted approved grade for %s.',
                        $teacherLoad->subject->name,
                        $gradingPeriod->quarter->label(),
                    );

                    continue;
                }

                $subjectGrades[] = (float) $quarterlyGrade->grade;

                if ($finalGradingPeriod !== null && $gradingPeriod->is($finalGradingPeriod)) {
                    $finalQuarterApplicable = true;
                    $finalQuarterReady = true;
                }
            }

            if ($hasApplicableQuarter) {
                $expectedSubjectCount++;
            }

            if ($finalQuarterApplicable) {
                $expectedFinalQuarterSubjectCount++;
            }

            $fullYearReady = $hasApplicableQuarter && $subjectBlockers === [];

            if ($finalQuarterReady && ($finalQuarterApplicable || $finalGradingPeriod === null || ! $hasApplicableQuarter)) {
                $finalQuarterReadyCount++;
            }

            if ($fullYearReady) {
                $fullYearReadyCount++;
            }

            $finalRating = $fullYearReady
                ? round((float) (collect($subjectGrades)->avg() ?? 0), (int) $rules['decimal_places'])
                : null;
            $actionTaken = $sectionRoster->year_end_status?->actionTakenLabel();

            $subjectRows[] = [
                'teacher_load_id' => $teacherLoad->id,
                'subject_name' => $teacherLoad->subject->name,
                'subject_code' => $teacherLoad->subject->code,
                'final_quarter_ready' => $finalQuarterReady,
                'full_year_ready' => $fullYearReady,
                'status' => $fullYearReady
                    ? ['label' => 'Ready', 'tone' => 'emerald']
                    : ['label' => 'Blocked', 'tone' => 'amber'],
                'final_rating' => $finalRating === null
                    ? null
                    : number_format($finalRating, (int) $rules['decimal_places'], '.', ''),
                'remarks' => $finalRating === null
                    ? null
                    : ($finalRating >= (float) $rules['passing'] ? 'Passed' : 'Failed'),
                'blockers' => $subjectBlockers,
                'action_taken' => $actionTaken,
                'applicable_for_year_end' => $hasApplicableQuarter,
            ];

            foreach ($subjectBlockers as $subjectBlocker) {
                $fullYearBlockers[] = $subjectBlocker;
            }

            if ($fullYearReady) {
                $approvedYearEndRows[] = [
                    'teacher_load_id' => $teacherLoad->id,
                    'subject_name' => $teacherLoad->subject->name,
                    'subject_code' => $teacherLoad->subject->code,
                    'final_rating' => number_format($finalRating, (int) $rules['decimal_places'], '.', ''),
                    'remarks' => $finalRating >= (float) $rules['passing'] ? 'Passed' : 'Failed',
                    'action_taken' => $actionTaken,
                ];
            }
        }

        $generalAverage = count($approvedYearEndRows) === $expectedSubjectCount && $approvedYearEndRows !== []
            ? number_format(
                (float) collect($approvedYearEndRows)->avg(fn (array $row): float => (float) $row['final_rating']),
                (int) $rules['decimal_places'],
                '.',
                '',
            )
            : null;

        $movementSummary = $this->eligibilityService->summary($sectionRoster);
        $hasMovementException = in_array($movementSummary['status']['value'], [
            EnrollmentStatus::TransferredOut->value,
            EnrollmentStatus::Dropped->value,
        ], true) || in_array($sectionRoster->year_end_status, [
            LearnerYearEndStatus::TransferredOut,
            LearnerYearEndStatus::Dropped,
        ], true);
        $standardPromotionAllowed = ! $hasMovementException
            && $expectedSubjectCount > 0
            && $gradingPeriods->isNotEmpty()
            && $fullYearReadyCount === $expectedSubjectCount;

        return [
            'section_roster_id' => $sectionRoster->id,
            'section' => $section,
            'sectionRoster' => $sectionRoster,
            'learner' => [
                'id' => $sectionRoster->learner->id,
                'name' => $this->learnerName($sectionRoster),
                'lrn' => $sectionRoster->learner->lrn,
            ],
            'grade_level_name' => $section->gradeLevel->name,
            'section_name' => $section->name,
            'school_year_name' => $section->schoolYear->name,
            'final_grading_period' => $finalGradingPeriod,
            'final_grading_period_label' => $finalGradingPeriod?->quarter->label(),
            'expected_subject_count' => $expectedSubjectCount,
            'expected_final_quarter_subject_count' => $expectedFinalQuarterSubjectCount,
            'final_quarter_ready_count' => $finalQuarterReadyCount,
            'full_year_ready_count' => $fullYearReadyCount,
            'final_quarter_ready' => $expectedFinalQuarterSubjectCount > 0
                && $finalGradingPeriod !== null
                && $finalQuarterReadyCount === $expectedFinalQuarterSubjectCount,
            'full_year_ready' => $expectedSubjectCount > 0
                && $gradingPeriods->isNotEmpty()
                && $fullYearReadyCount === $expectedSubjectCount,
            'full_year_blockers' => array_values(array_unique($fullYearBlockers)),
            'subject_rows' => $subjectRows,
            'approved_year_end_rows' => $approvedYearEndRows,
            'general_average' => $generalAverage,
            'enrollment_context' => [
                'roster_status' => [
                    'label' => $movementSummary['status']['label'],
                    'tone' => $movementSummary['status']['tone'],
                ],
                'learner_status' => [
                    'label' => $sectionRoster->learner->enrollment_status->label(),
                    'tone' => $sectionRoster->learner->enrollment_status->tone(),
                ],
                'effective_date' => $movementSummary['effective_date_label'],
                'movement_reason' => $movementSummary['reason'],
            ],
            'year_end_status' => $sectionRoster->year_end_status === null ? null : [
                'value' => $sectionRoster->year_end_status->value,
                'label' => $sectionRoster->year_end_status->label(),
                'tone' => $sectionRoster->year_end_status->tone(),
                'requires_reason' => $sectionRoster->year_end_status->requiresReason(),
            ],
            'year_end_status_reason' => $sectionRoster->year_end_status_reason,
            'year_end_status_set_at' => $sectionRoster->year_end_status_set_at?->format('M d, Y g:i A'),
            'year_end_status_set_by' => $sectionRoster->yearEndStatusSetBy?->name,
            'standard_promotion_allowed' => $standardPromotionAllowed,
            'transfer_context' => $hasMovementException,
        ];
    }

    private function emptySummary(Section $section, SectionRoster $sectionRoster): array
    {
        $section->loadMissing(['schoolYear:id,name', 'gradeLevel:id,name']);
        $sectionRoster->loadMissing('learner');

        return [
            'section_roster_id' => $sectionRoster->id,
            'section' => $section,
            'sectionRoster' => $sectionRoster,
            'learner' => [
                'id' => $sectionRoster->learner->id,
                'name' => $this->learnerName($sectionRoster),
                'lrn' => $sectionRoster->learner->lrn,
            ],
            'grade_level_name' => $section->gradeLevel->name,
            'section_name' => $section->name,
            'school_year_name' => $section->schoolYear->name,
            'final_grading_period' => null,
            'final_grading_period_label' => null,
            'expected_subject_count' => 0,
            'expected_final_quarter_subject_count' => 0,
            'final_quarter_ready_count' => 0,
            'full_year_ready_count' => 0,
            'final_quarter_ready' => false,
            'full_year_ready' => false,
            'full_year_blockers' => ['No grading data exists for this learner yet.'],
            'subject_rows' => [],
            'approved_year_end_rows' => [],
            'general_average' => null,
            'enrollment_context' => [
                'roster_status' => [
                    'label' => $sectionRoster->enrollment_status->label(),
                    'tone' => $sectionRoster->enrollment_status->tone(),
                ],
                'learner_status' => [
                    'label' => $sectionRoster->learner->enrollment_status->label(),
                    'tone' => $sectionRoster->learner->enrollment_status->tone(),
                ],
                'effective_date' => null,
                'movement_reason' => null,
            ],
            'year_end_status' => null,
            'year_end_status_reason' => null,
            'year_end_status_set_at' => null,
            'year_end_status_set_by' => null,
            'standard_promotion_allowed' => false,
            'transfer_context' => false,
        ];
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
}
