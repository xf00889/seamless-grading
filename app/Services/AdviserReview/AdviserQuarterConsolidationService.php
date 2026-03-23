<?php

namespace App\Services\AdviserReview;

use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\QuarterlyGrade;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdviserQuarterConsolidationService
{
    public function __construct(
        private readonly AdviserQuarterContextResolver $contextResolver,
        private readonly AdviserQuarterReviewService $reviewService,
        private readonly LearnerMovementEligibilityService $eligibilityService,
    ) {}

    public function byLearner(Section $section, GradingPeriod $gradingPeriod, array $filters): array
    {
        $this->contextResolver->assertSectionPeriodScope($section, $gradingPeriod);

        $search = trim((string) ($filters['search'] ?? ''));
        $approvedSubmissions = $this->approvedSubmissionCollection($section, $gradingPeriod);
        $approvedSubmissionIds = $approvedSubmissions->pluck('id');

        $sectionRosters = $this->officialRosterQuery($section, $search)
            ->paginate(10)
            ->withQueryString();

        $quarterlyGrades = QuarterlyGrade::query()
            ->whereIn('grade_submission_id', $approvedSubmissionIds)
            ->whereIn('section_roster_id', collect($sectionRosters->items())->pluck('id'))
            ->get(['grade_submission_id', 'section_roster_id', 'grade', 'remarks'])
            ->groupBy('section_roster_id');

        $sectionRosters->through(function (SectionRoster $sectionRoster) use ($approvedSubmissions, $gradingPeriod, $quarterlyGrades): array {
            $grades = $quarterlyGrades->get($sectionRoster->id, collect())->keyBy('grade_submission_id');
            $eligibility = $this->eligibilityService->forGradingPeriod($sectionRoster, $gradingPeriod);

            return [
                'section_roster_id' => $sectionRoster->id,
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
                'eligibility_note' => $eligibility['reason'],
                'grades' => $approvedSubmissions
                    ->mapWithKeys(fn (GradeSubmission $gradeSubmission): array => [
                        $gradeSubmission->id => [
                            'grade' => $eligibility['accepts_grade'] && $grades->get($gradeSubmission->id)?->grade !== null
                                ? number_format((float) $grades->get($gradeSubmission->id)->grade, 2, '.', '')
                                : null,
                            'remarks' => $eligibility['accepts_grade']
                                ? $grades->get($gradeSubmission->id)?->remarks
                                : null,
                        ],
                    ])
                    ->all(),
            ];
        });

        return [
            'section' => $section->loadMissing([
                'schoolYear:id,name',
                'gradeLevel:id,name',
                'adviser:id,name',
            ]),
            'gradingPeriod' => $gradingPeriod,
            'filters' => [
                'search' => $search,
            ],
            'summary' => $this->reviewService->sectionSummary($section, $gradingPeriod),
            'subjectColumns' => $approvedSubmissions
                ->map(fn (GradeSubmission $gradeSubmission): array => [
                    'grade_submission_id' => $gradeSubmission->id,
                    'subject_name' => $gradeSubmission->teacherLoad->subject->name,
                    'subject_code' => $gradeSubmission->teacherLoad->subject->code,
                    'teacher_name' => $gradeSubmission->teacherLoad->teacher->name,
                ])
                ->values()
                ->all(),
            'learners' => $sectionRosters,
        ];
    }

    public function bySubject(Section $section, GradingPeriod $gradingPeriod, array $filters): array
    {
        $this->contextResolver->assertSectionPeriodScope($section, $gradingPeriod);

        $search = trim((string) ($filters['search'] ?? ''));
        $officialRosters = $this->officialRosterQuery($section, '')
            ->get();

        $approvedSubmissions = GradeSubmission::query()
            ->with([
                'teacherLoad.subject:id,name,code',
                'teacherLoad.teacher:id,name',
                'quarterlyGrades' => fn ($query) => $query
                    ->whereHas('sectionRoster', fn (Builder $sectionRosterQuery) => $sectionRosterQuery
                        ->where('section_id', $section->id)
                        ->where('school_year_id', $section->school_year_id)
                        ->where('is_official', true)),
            ])
            ->join('teacher_loads', 'teacher_loads.id', '=', 'grade_submissions.teacher_load_id')
            ->join('subjects', 'subjects.id', '=', 'teacher_loads.subject_id')
            ->select('grade_submissions.*')
            ->where('grade_submissions.grading_period_id', $gradingPeriod->id)
            ->where('grade_submissions.status', GradeSubmissionStatus::Approved)
            ->where('teacher_loads.section_id', $section->id)
            ->where('teacher_loads.school_year_id', $section->school_year_id)
            ->where('teacher_loads.is_active', true)
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $submissionQuery) use ($search): void {
                        $submissionQuery
                            ->where('subjects.name', 'like', '%'.$search.'%')
                            ->orWhere('subjects.code', 'like', '%'.$search.'%')
                            ->orWhereHas('teacherLoad.teacher', fn (Builder $teacherQuery) => $teacherQuery
                                ->where('name', 'like', '%'.$search.'%'));
                    });
                },
            )
            ->orderBy('subjects.name')
            ->paginate(5)
            ->withQueryString();

        $approvedSubmissions->through(function (GradeSubmission $gradeSubmission) use ($officialRosters, $gradingPeriod): array {
            $gradesByRoster = $gradeSubmission->quarterlyGrades->keyBy('section_roster_id');

            return [
                'grade_submission_id' => $gradeSubmission->id,
                'subject_name' => $gradeSubmission->teacherLoad->subject->name,
                'subject_code' => $gradeSubmission->teacherLoad->subject->code,
                'teacher_name' => $gradeSubmission->teacherLoad->teacher->name,
                'approved_at' => $gradeSubmission->approved_at?->format('M d, Y g:i A'),
                'learners' => $officialRosters
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
                            'grade' => $eligibility['accepts_grade'] && $grade?->grade !== null
                                ? number_format((float) $grade->grade, 2, '.', '')
                                : null,
                            'remarks' => $eligibility['accepts_grade'] ? $grade?->remarks : $eligibility['reason'],
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        });

        return [
            'section' => $section->loadMissing([
                'schoolYear:id,name',
                'gradeLevel:id,name',
                'adviser:id,name',
            ]),
            'gradingPeriod' => $gradingPeriod,
            'filters' => [
                'search' => $search,
            ],
            'summary' => $this->reviewService->sectionSummary($section, $gradingPeriod),
            'submissions' => $approvedSubmissions,
        ];
    }

    /**
     * @return Collection<int, GradeSubmission>
     */
    private function approvedSubmissionCollection(Section $section, GradingPeriod $gradingPeriod): Collection
    {
        return GradeSubmission::query()
            ->with([
                'teacherLoad.subject:id,name,code',
                'teacherLoad.teacher:id,name',
            ])
            ->where('grading_period_id', $gradingPeriod->id)
            ->where('status', GradeSubmissionStatus::Approved)
            ->whereHas('teacherLoad', fn (Builder $query) => $query
                ->where('section_id', $section->id)
                ->where('school_year_id', $section->school_year_id)
                ->where('is_active', true))
            ->get()
            ->sortBy(fn (GradeSubmission $gradeSubmission): string => mb_strtolower(
                $gradeSubmission->teacherLoad->subject->name.' '.$gradeSubmission->teacherLoad->teacher->name,
            ))
            ->values();
    }

    private function officialRosterQuery(Section $section, string $search): Builder
    {
        return SectionRoster::query()
            ->with('learner')
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->select('section_rosters.*')
            ->where('section_rosters.section_id', $section->id)
            ->where('section_rosters.school_year_id', $section->school_year_id)
            ->where('section_rosters.is_official', true)
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $sectionRosterQuery) use ($search): void {
                        $sectionRosterQuery
                            ->where('learners.first_name', 'like', '%'.$search.'%')
                            ->orWhere('learners.last_name', 'like', '%'.$search.'%')
                            ->orWhere('learners.middle_name', 'like', '%'.$search.'%')
                            ->orWhere('learners.lrn', 'like', '%'.$search.'%');
                    });
                },
            )
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name');
    }
}
